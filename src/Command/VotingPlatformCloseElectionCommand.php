<?php

namespace App\Command;

use App\Entity\VotingPlatform\CandidateGroup;
use App\Entity\VotingPlatform\Election;
use App\Entity\VotingPlatform\ElectionResult\CandidateGroupResult;
use App\Entity\VotingPlatform\ElectionResult\ElectionPoolResult;
use App\Entity\VotingPlatform\ElectionResult\ElectionResult;
use App\Entity\VotingPlatform\ElectionResult\ElectionRoundResult;
use App\Entity\VotingPlatform\ElectionRound;
use App\Repository\CommitteeElectionRepository;
use App\Repository\VotingPlatform\ElectionRepository;
use App\Repository\VotingPlatform\VoteResultRepository;
use App\VotingPlatform\Designation\DesignationTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class VotingPlatformCloseElectionCommand extends Command
{
    protected static $defaultName = 'app:voting-platform:close-election';

    /** @var SymfonyStyle */
    private $io;
    /** @var ElectionRepository */
    private $electionRepository;
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var VoteResultRepository */
    private $voteResultRepository;
    /** @var CommitteeElectionRepository */
    private $committeeElectionRepository;

    protected function configure()
    {
        $this->setDescription('Voting Platform: close election');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = new \DateTime();

        $this->io->progressStart();

        while ($elections = $this->electionRepository->getElectionsToClose($date)) {
            foreach ($elections as $election) {
                $this->closeElection($election);

                $this->io->progressAdvance();
            }

            $this->entityManager->clear();
        }

        $this->io->progressFinish();
    }

    private function closeElection(Election $election): void
    {
        // 1. compute election result
        $electionResult = $this->computeElectionResult($election);
        $this->entityManager->persist($electionResult);

        // 2. close election OR start second round
        if ($electionResult->isFullyElected($currentRound = $election->getCurrentRound())) {
            $this->doElectionClose($election);
        } else {
            $election->startSecondRound($electionResult->findForSecondRoundPools($currentRound));
        }

        $this->entityManager->flush();
    }

    /**
     * @return CandidateGroup[]
     */
    private function findElected(array $candidateGroups, array $results): array
    {
        if (empty($results)) {
            return [];
        }

        $uuids = array_map(function (CandidateGroup $group) {
            return $group->getUuid()->toString();
        }, $candidateGroups);

        $resultsForCurrentGroups = array_intersect_key($results, array_flip($uuids));

        $maxScore = max($resultsForCurrentGroups);
        $winnerUuids = array_keys(array_filter($resultsForCurrentGroups, function (int $score) use ($maxScore) {
            return $score === $maxScore;
        }));

        return array_filter($candidateGroups, function (CandidateGroup $group) use ($winnerUuids) {
            return \in_array($group->getUuid()->toString(), $winnerUuids);
        });
    }

    /** @required */
    public function setElectionRepository(ElectionRepository $electionRepository): void
    {
        $this->electionRepository = $electionRepository;
    }

    /** @required */
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    /** @required */
    public function setVoteResultRepository(VoteResultRepository $voteResultRepository): void
    {
        $this->voteResultRepository = $voteResultRepository;
    }

    /** @required */
    public function setCommitteeElectionRepository(CommitteeElectionRepository $committeeElectionRepository): void
    {
        $this->committeeElectionRepository = $committeeElectionRepository;
    }

    private function computeElectionResult(Election $election): ElectionResult
    {
        if (!$electionResult = $election->getElectionResult()) {
            $electionResult = new ElectionResult($election);
        }

        $currentRound = $election->getCurrentRound();

        if ($electionResult->alreadyFilledForRound($currentRound)) {
            return $electionResult;
        }

        $electionRoundResult = $this->createElectionRoundResultObject($currentRound);
        $electionResult->addElectionRoundResult($electionRoundResult);

        $voteResults = $this->voteResultRepository->getResults($currentRound);

        foreach ($voteResults as $voteResult) {
            $electionRoundResult->updateFromNewVoteResult($voteResult);
        }

        $electionRoundResult->sync();

        return $electionResult;
    }

    private function createElectionRoundResultObject(ElectionRound $electionRound): ElectionRoundResult
    {
        $electionRoundResult = new ElectionRoundResult($electionRound);

        foreach ($electionRound->getElectionPools() as $pool) {
            $electionRoundResult->addElectionPoolResult($poolResult = new ElectionPoolResult($pool));

            foreach ($pool->getCandidateGroups() as $candidateGroup) {
                $poolResult->addCandidateGroupResult(new CandidateGroupResult($candidateGroup));
            }
        }

        return $electionRoundResult;
    }

    private function doElectionClose(Election $election): void
    {
        $election->close();

        if (DesignationTypeEnum::COMMITTEE_ADHERENT === $election->getDesignationType()) {
//            $committeeElection = $this->committeeElectionRepository->findForCommittee($election->getElectionEntity()->getCommittee());

//            if ($committeeElection) {
//                $committeeElection->archive();
//            }
        }
    }
}
