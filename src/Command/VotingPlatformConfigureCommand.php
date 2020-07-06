<?php

namespace App\Command;

use App\Entity\CommitteeElection;
use App\Entity\VotingPlatform\Candidate;
use App\Entity\VotingPlatform\CandidateGroup;
use App\Entity\VotingPlatform\Designation\Designation;
use App\Entity\VotingPlatform\Election;
use App\Entity\VotingPlatform\ElectionEntity;
use App\Entity\VotingPlatform\ElectionPool;
use App\Entity\VotingPlatform\ElectionRound;
use App\Entity\VotingPlatform\Voter;
use App\Entity\VotingPlatform\VotersList;
use App\Repository\CommitteeCandidacyRepository;
use App\Repository\CommitteeElectionRepository;
use App\Repository\CommitteeMembershipRepository;
use App\Repository\VotingPlatform\DesignationRepository;
use App\Repository\VotingPlatform\ElectionRepository;
use App\Repository\VotingPlatform\VoterRepository;
use App\VotingPlatform\Designation\DesignationTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class VotingPlatformConfigureCommand extends Command
{
    protected static $defaultName = 'app:voting-platform:configure';

    /** @var DesignationRepository */
    private $designationRepository;
    /** @var CommitteeElectionRepository */
    private $committeeElectionRepository;
    /** @var SymfonyStyle */
    private $io;
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var ElectionRepository */
    private $electionRepository;
    /** @var CommitteeCandidacyRepository */
    private $committeeCandidacyRepository;
    /** @var CommitteeMembershipRepository */
    private $committeeMembershipRepository;
    /** @var VoterRepository */
    private $voterRepository;

    protected function configure()
    {
        $this->setDescription('Configure Voting Platform: create Election and voters/candidates lists');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = new \DateTime();

        $designations = $this->designationRepository->getIncomingDesignations($date);

        $this->io->progressStart();

        foreach ($designations as $designation) {
            if (DesignationTypeEnum::COMMITTEE_ADHERENT === $designation->getType()) {
                $this->configureCommitteeElections($designation);
            } else {
                $this->io->error(sprintf('Unhandled designation type "%s"', $designation->getType()));
            }
        }

        $this->io->progressFinish();
    }

    private function configureCommitteeElections(Designation $designation): void
    {
        $offset = 0;

        while ($committeeElections = $this->committeeElectionRepository->findAllByDesignation($designation, $offset)) {
            foreach ($committeeElections as $committeeElection) {
                $committee = $committeeElection->getCommittee();

                $this->io->progressAdvance();

                if ($this->electionRepository->hasElectionForCommittee($committee, $designation)) {
                    continue;
                }

                if (!$this->isValidCommitteeElection($committeeElection)) {
                    continue;
                }

                $election = new Election($designation, null, [new ElectionRound()]);
                $election->setElectionEntity(new ElectionEntity($committee));

                $this->configureNewElection($election);
            }

            $this->entityManager->clear();

            $designation = $this->entityManager->merge($designation);

            $offset += \count($committeeElections);
        }
    }

    private function configureNewElection(Election $election): void
    {
        $electionRound = $election->getCurrentRound();
        $committee = $election->getElectionEntity()->getCommittee();

        // Create candidates groups
        $candidacies = $this->committeeCandidacyRepository->findByCommittee($committee);

        $womanPool = new ElectionPool('Femme');
        $manPool = new ElectionPool('Homme');

        foreach ($candidacies as $candidacy) {
            $adherent = $candidacy->getCommitteeMembership()->getAdherent();

            $group = new CandidateGroup();
            $group->addCandidate($candidate = new Candidate(
                $adherent->getFirstName(),
                $adherent->getLastName(),
                $candidacy->getGender()
            ));

            $candidate->setImagePath($candidacy->getImagePath());
            $candidate->setBiography($candidacy->getBiography());

            if ($candidate->isWoman()) {
                $womanPool->addCandidateGroup($group);
            } else {
                $manPool->addCandidateGroup($group);
            }
        }

        if ($womanPool->getCandidateGroups()) {
            $electionRound->addElectionPool($womanPool);
            $election->addElectionPool($womanPool);
        }

        if ($manPool->getCandidateGroups()) {
            $electionRound->addElectionPool($manPool);
            $election->addElectionPool($manPool);
        }

        $memberships = $this->committeeMembershipRepository->findVotingMemberships($committee);

        $list = new VotersList($election);

        foreach ($memberships as $membership) {
            $adherent = $membership->getAdherent();

            $list->addVoter($this->voterRepository->findForAdherent($adherent) ?? new Voter($adherent));
        }

        $this->entityManager->persist($list);
        $this->entityManager->persist($election);
        $this->entityManager->flush();
    }

    private function isValidCommitteeElection(CommitteeElection $committeeElection): bool
    {
        $committee = $committeeElection->getCommittee();

        // validate voters
        if (!$this->committeeMembershipRepository->committeeHasVoters($committee)) {
            if ($this->io->isDebug()) {
                $this->io->warning(sprintf('Committee "%s" does not have any voters', $committee->getSlug()));
            }

            return false;
        }

        return true;
    }

    /** @required */
    public function setDesignationRepository(DesignationRepository $designationRepository): void
    {
        $this->designationRepository = $designationRepository;
    }

    /** @required */
    public function setCommitteeElectionRepository(CommitteeElectionRepository $committeeElectionRepository): void
    {
        $this->committeeElectionRepository = $committeeElectionRepository;
    }

    /** @required */
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    /** @required */
    public function setElectionRepository(ElectionRepository $electionRepository): void
    {
        $this->electionRepository = $electionRepository;
    }

    /** @required */
    public function setCommitteeCandidacyRepository(CommitteeCandidacyRepository $committeeCandidacyRepository): void
    {
        $this->committeeCandidacyRepository = $committeeCandidacyRepository;
    }

    /** @required */
    public function setCommitteeMembershipRepository(CommitteeMembershipRepository $committeeMembershipRepository): void
    {
        $this->committeeMembershipRepository = $committeeMembershipRepository;
    }

    /** @required */
    public function setVoterRepository(VoterRepository $voterRepository): void
    {
        $this->voterRepository = $voterRepository;
    }
}
