<?php

namespace App\Entity\VotingPlatform\ElectionResult;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use App\Entity\EntityIdentityTrait;
use App\Entity\VotingPlatform\ElectionPool;
use App\Entity\VotingPlatform\ElectionRound;
use App\Entity\VotingPlatform\VoteResult;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="voting_platform_election_round_result")
 *
 * @Algolia\Index(autoIndex=false)
 */
class ElectionRoundResult
{
    use EntityIdentityTrait;

    /**
     * @var ElectionRound
     *
     * @ORM\OneToOne(targetEntity="App\Entity\VotingPlatform\ElectionRound")
     */
    private $electionRound;

    /**
     * @var ElectionResult
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\VotingPlatform\ElectionResult\ElectionResult", inversedBy="electionRoundResults")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $electionResult;

    /**
     * @var ElectionPoolResult[]|Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\VotingPlatform\ElectionResult\ElectionPoolResult", mappedBy="electionRoundResult", cascade={"all"})
     */
    private $electionPoolResults;

    public function __construct(ElectionRound $electionRound, UuidInterface $uuid = null)
    {
        $this->electionRound = $electionRound;
        $this->uuid = $uuid ?? Uuid::uuid4();
        $this->electionPoolResults = new ArrayCollection();
    }

    /**
     * @return ElectionPoolResult[]
     */
    public function getElectedPools(): array
    {
        return $this->electionPoolResults->filter(function (ElectionPoolResult $poolResult) {
            return $poolResult->isElected();
        })->toArray();
    }

    public function getElectionRound(): ElectionRound
    {
        return $this->electionRound;
    }

    public function addElectionPoolResult(ElectionPoolResult $result): void
    {
        if (!$this->electionPoolResults->contains($result)) {
            $result->setElectionRoundResult($this);
            $this->electionPoolResults->add($result);
        }
    }

    public function updateFromNewVoteResult(VoteResult $voteResult): void
    {
        foreach ($voteResult->getVoteChoices() as $voteChoice) {
            $pool = $voteChoice->getElectionPool();
            $poolResult = $this->findElectedPoolResult($pool);

            $poolResult->updateFromNewVoteChoice($voteChoice);
        }
    }

    private function findElectedPoolResult(ElectionPool $pool): ?ElectionPoolResult
    {
        foreach ($this->electionPoolResults as $poolResult) {
            if ($poolResult->getElectionPool() === $pool) {
                return $poolResult;
            }
        }

        return null;
    }

    public function setElectionResult(ElectionResult $electionResult): void
    {
        $this->electionResult = $electionResult;
    }

    public function sync(): void
    {
        foreach ($this->electionPoolResults as $poolResult) {
            $poolResult->sync();
        }
    }

    public function isFullyElected(): bool
    {
        foreach ($this->electionPoolResults as $poolResult) {
            if (!$poolResult->isElected()) {
                return false;
            }
        }

        return !$this->electionPoolResults->isEmpty();
    }

    /**
     * @return ElectionPool[]
     */
    public function getNotElectedPools(): array
    {
        $pools = [];

        foreach ($this->electionPoolResults as $electionPoolResult) {
            if (!$electionPoolResult->isElected()) {
                $pools[] = $electionPoolResult->getElectionPool();
            }
        }

        return $pools;
    }
}
