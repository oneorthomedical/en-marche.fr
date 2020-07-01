<?php

namespace App\Command\Geo;

use App\Entity\Geo\City;
use App\Entity\Geo\Country;
use App\Entity\Geo\Department;
use App\Entity\Geo\Region;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class UpdateFranceCommand extends Command
{
    private const FRANCE_CODE = 'FR';
    private const API_PATH = '/communes?fields=code,nom,codesPostaux,population,departement,region';

    protected static $defaultName = 'app:geo:update-france';

    /**
     * @var HttpClientInterface
     */
    private $apiClient;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var Collection
     */
    private $entities;

    public function __construct(HttpClientInterface $geoGouvApiClient, EntityManagerInterface $em)
    {
        $this->apiClient = $geoGouvApiClient;
        $this->em = $em;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Update french administrative divisions')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Execute the algorithm without persisting any data.')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->entities = new ArrayCollection();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Start updating french administrative division');

        //
        // Processing data
        //

        $this->io->section('Requesting API');

        $entries = $this->apiClient->request('GET', self::API_PATH)->toArray();

        $this->io->success(sprintf('%d cities found', \count($entries)));

        $this->io->section('Processing entries');

        $this->io->progressStart(\count($entries));
        foreach ($entries as $entry) {
            $this->processEntry($entry);
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        $this->io->success('Done');

        //
        // Summary
        //

        $this->summary();

        //
        // Writing
        //

        $this->io->section('Persisting in database');

        $dryRun = $input->getOption('dry-run');
        if ($dryRun) {
            $this->io->comment('Nothing was persisted in database');

            return 0;
        }

        foreach ($this->entities as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $this->io->success('Done');

        return 0;
    }

    private function processEntry(array $entry): void
    {
        $france = $this->retrieveEntity(Country::class, self::FRANCE_CODE, static function () {
            return new Country(self::FRANCE_CODE, 'France');
        });

        /* @var Region|null $region */
        $region = null;
        if (isset($entry['region']['code'])) {
            $region = $this->retrieveEntity(
                Region::class,
                $entry['region']['code'],
                static function () use ($entry, $france): Region {
                    return new Region(
                        $entry['region']['code'],
                        $entry['region']['nom'],
                        $france
                    );
                }
            );

            $region->setName($entry['region']['nom']);
        }

        /* @var Department|null $department */
        $department = null;
        if ($region && isset($entry['departement']['code'])) {
            $department = $this->retrieveEntity(
                Department::class,
                $entry['departement']['code'],
                static function () use ($entry, $region): Department {
                    return new Department(
                        $entry['departement']['code'],
                        $entry['departement']['nom'],
                        $region
                    );
                }
            );

            $department->setName($entry['departement']['nom']);
            $department->setRegion($region);
        }

        /* @var City $city */
        $city = $this->retrieveEntity(
            City::class,
            $entry['code'],
            static function () use ($entry): City {
                return new City($entry['code'], $entry['nom']);
            }
        );

        $city->setName($entry['nom']);
        $city->setPostalCode($entry['codesPostaux'] ?? []);
        $city->setPopulation($entry['population'] ?? null);
        $city->setDepartment($department);
    }

    /**
     * @return Country|Region|Department|City
     *
     * @throws \RuntimeException When entity doesn't exist in database and $factory argument isn't given
     */
    private function retrieveEntity(string $class, string $code, callable $factory = null): object
    {
        $key = $class.'#'.$code;

        if (!$this->entities->containsKey($key)) {
            $repository = $this->em->getRepository($class);

            /* @var Country|Region|Department|City $entity */
            $entity = $repository->findOneBy(['code' => $code]);
            if (!$entity) {
                if (!$factory) {
                    throw new \RuntimeException(sprintf('Entity %s not found', $key));
                }

                $entity = $factory();
            }

            $this->entities->set($key, $entity);
        }

        return $this->entities->get($key);
    }

    private function summary(): void
    {
        $this->io->section('Summary');

        /* @var Collection|Region[] $newRegions */
        $newRegions = $this->entities->filter(static function ($entity) {
            return $entity instanceof Region && !$entity->getCreatedAt();
        });

        $this->io->note(sprintf('%d new regions', $newRegions->count()));
        if ($this->io->isVerbose()) {
            foreach ($newRegions as $region) {
                $this->io->text(sprintf('%s (%s)', $region->getName(), $region->getCode()));
            }
        }

        /* @var Collection|Department[] $newDepartments */
        $newDepartments = $this->entities->filter(static function ($entity) {
            return $entity instanceof Department && !$entity->getCreatedAt();
        });

        $this->io->note(sprintf('%d new departments', $newDepartments->count()));
        if ($this->io->isVerbose()) {
            foreach ($newDepartments as $department) {
                $this->io->text(sprintf('%s (%s)', $department->getName(), $department->getCode()));
            }
        }

        /* @var Collection|City[] $newCities */
        $newCities = $this->entities->filter(static function ($entity) {
            return $entity instanceof City && !$entity->getCreatedAt();
        });

        $this->io->note(sprintf('%d new cities', $newCities->count()));
        if ($this->io->isVerbose()) {
            foreach ($newCities as $city) {
                $this->io->text(sprintf('%s (%s)', $city->getName(), $city->getCode()));
            }
        }
    }
}
