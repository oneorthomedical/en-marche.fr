<?php

namespace App\Command;

use App\Entity\VotingPlatform\Designation\Designation;
use App\Entity\VotingPlatform\Election;
use App\Entity\VotingPlatform\ElectionResult\CandidateGroupResult;
use App\Entity\VotingPlatform\ElectionResult\ElectionPoolResult;
use App\Entity\VotingPlatform\ElectionResult\ElectionResult;
use App\Entity\VotingPlatform\ElectionResult\ElectionRoundResult;
use App\Entity\VotingPlatform\ElectionRound;
use App\Repository\CommitteeElectionRepository;
use App\Repository\CommitteeMembershipRepository;
use App\Repository\VotingPlatform\DesignationRepository;
use App\Repository\VotingPlatform\ElectionRepository;
use App\Repository\VotingPlatform\VoteResultRepository;
use App\Repository\VotingPlatform\VoterRepository;
use App\VotingPlatform\Designation\DesignationTypeEnum;
use App\VotingPlatform\Events;
use App\VotingPlatform\Notifier\Event\CommitteeElectionCandidacyPeriodIsOverEvent;
use App\VotingPlatform\Notifier\Event\CommitteeElectionVoteIsOverEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class VotingPlatformCloseElectionCommand extends Command
{
    protected static $defaultName = 'app:voting-platform:close-election';

    /** @var SymfonyStyle */
    private $io;
    /** @var ElectionRepository */
    private $electionRepository;
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var DesignationRepository */
    private $designationRepository;
    /** @var CommitteeElectionRepository */
    private $committeeElectionRepository;
    /** @var CommitteeMembershipRepository */
    private $committeeMembershipRepository;
    /** @var EventDispatcherInterface */
    private $dispatcher;
    /** @var VoteResultRepository */
    private $voteResultRepository;
    /** @var VoterRepository */
    private $voterRepository;

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
        $this->closeElections();

        $this->notifyForEndForCandidacy();
    }

    private function closeElections(): void
    {
        $date = new \DateTime();

        $this->io->progressStart();

        while ($elections = $this->electionRepository->getElectionsToClose($date, 50)) {
            foreach ($elections as $election) {
                $this->doCloseElection($election);

                $this->io->progressAdvance();
            }

            $this->entityManager->clear();
        }

        $this->io->progressFinish();
    }

    private function doCloseElection(Election $election): void
    {
        // 1. compute election result
        $electionResult = $this->computeElectionResult($election);

        // 2. close election or start the second round
        if ($election->canClose()) {
            $election->close();
        } else {
            $election->startSecondRound($electionResult->getNotElectedPools($election->getCurrentRound()));
        }

        if (!$electionResult->getId()) {
            $this->entityManager->persist($electionResult);
        }

        $this->notifyEndOfElectionRound($election);

        $this->entityManager->flush();
    }

    private function computeElectionResult(Election $election): ElectionResult
    {
        if (!$electionResult = $election->getElectionResult()) {
            $electionResult = new ElectionResult($election);
            $electionResult->setParticipated($this->voterRepository->countForElection($election));

            $election->setElectionResult($electionResult);
        }

        $currentRound = $election->getCurrentRound();

        if ($electionResult->alreadyFilledForRound($currentRound)) {
            return $electionResult;
        }

        $electionRoundResult = $this->createElectionRoundResultObject($currentRound);
        $electionResult->addElectionRoundResult($electionRoundResult);

        $voteResults = $this->voteResultRepository->getResultsForRound($currentRound);

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

    private function notifyForEndForCandidacy(): void
    {
        $date = new \DateTime();

        $designations = $this->designationRepository->getWithFinishCandidacyPeriod($date);

        $this->io->progressStart();

        foreach ($designations as $designation) {
            if (DesignationTypeEnum::COMMITTEE_ADHERENT === $designation->getType()) {
                $this->notifyCommitteeElections($designation);
            }
        }

        $this->io->progressFinish();
    }

    public function notifyCommitteeElections(Designation $designation): void
    {
        while ($committeeElections = $this->committeeElectionRepository->findAllToNotify($designation)) {
            foreach ($committeeElections as $committeeElection) {
                $memberships = $this->committeeMembershipRepository->findVotingMemberships($committee = $committeeElection->getCommittee());

                foreach ($memberships as $membership) {
                    $this->dispatcher->dispatch(Events::CANDIDACY_PERIOD_CLOSE, new CommitteeElectionCandidacyPeriodIsOverEvent(
                        $membership->getAdherent(),
                        $designation,
                        $committee
                    ));
                }

                $committeeElection->setAdherentNotified(true);

                $this->entityManager->flush();

                $this->io->progressAdvance();
            }

            $this->entityManager->clear();
        }
    }

    private function notifyEndOfElectionRound(Election $election): void
    {
        $committee = $election->getElectionEntity()->getCommittee();

        $memberships = $this->committeeMembershipRepository->findVotingMemberships($committee);

        foreach ($memberships as $membership) {
            $this->dispatcher->dispatch(Events::VOTE_CLOSE, new CommitteeElectionVoteIsOverEvent(
                $membership->getAdherent(),
                $election->getDesignation(),
                $committee
            ));
        }
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
    public function setCommitteeMembershipRepository(CommitteeMembershipRepository $committeeMembershipRepository): void
    {
        $this->committeeMembershipRepository = $committeeMembershipRepository;
    }

    /** @required */
    public function setDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /** @required */
    public function setVoteResultRepository(VoteResultRepository $voteResultRepository): void
    {
        $this->voteResultRepository = $voteResultRepository;
    }

    /** @required */
    public function setVoterRepository(VoterRepository $voterRepository): void
    {
        $this->voterRepository = $voterRepository;
    }
}
