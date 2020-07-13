<?php

namespace App\Repository\VotingPlatform;

use App\Entity\VotingPlatform\Election;
use App\Entity\VotingPlatform\ElectionRound;
use App\Entity\VotingPlatform\VoteResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class VoteResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoteResult::class);
    }

    /**
     * @return VoteResult[]
     */
    public function getResultsForRound(ElectionRound $electionRound): array
    {
        return $this->createQueryBuilder('vr')
            ->addSelect('vc', 'cg', 'c')
            ->innerJoin('vr.voteChoices', 'vc')
            ->leftJoin('vc.candidateGroup', 'cg')
            ->leftJoin('cg.candidates', 'c')
            ->where('vr.electionRound = :election_round')
            ->setParameter('election_round', $electionRound)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return VoteResult[]
     */
    public function getResultsForElection(Election $election): array
    {
        return $this->createQueryBuilder('vr')
            ->addSelect('vc', 'cg', 'c')
            ->innerJoin('vr.voteChoices', 'vc')
            ->innerJoin('vr.electionRound', 'er')
            ->leftJoin('vc.candidateGroup', 'cg')
            ->leftJoin('cg.candidates', 'c')
            ->where('er.election = :election')
            ->setParameter('election', $election)
            ->orderBy('vr.votedAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
