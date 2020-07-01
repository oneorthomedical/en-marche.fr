<?php

namespace App\Entity\VotingPlatform\ElectionResult;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use App\Entity\EntityIdentityTrait;
use App\Entity\VotingPlatform\Election;
use App\Entity\VotingPlatform\ElectionRound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="voting_platform_election_result")
 *
 * @Algolia\Index(autoIndex=false)
 */
class ElectionResult
{
    use EntityIdentityTrait;

    /**
     * @var Election
     *
     * @ORM\OneToOne(targetEntity="App\Entity\VotingPlatform\Election", inversedBy="electionResult")
     */
    private $election;

    /**
     * @var ElectionRoundResult[]|Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\VotingPlatform\ElectionResult\ElectionRoundResult", mappedBy="electionResult", cascade={"all"})
     */
    private $electionRoundResults;

    public function __construct(Election $election, UuidInterface $uuid = null)
    {
        $this->election = $election;
        $this->uuid = $uuid ?? Uuid::uuid4();

        $this->electionRoundResults = new ArrayCollection();
    }

    /**
     * @return CandidateGroupResult[]
     */
    public function getElectedPools(): array
    {
        $electedPools = [];
        foreach ($this->electionRoundResults as $roundResult) {
            array_push($electedPools, ...$roundResult->getElectedPools());
        }

        return $electedPools;
    }

    public function alreadyFilledForRound(ElectionRound $electionRound): bool
    {
        foreach ($this->electionRoundResults as $result) {
            if ($result->getElectionRound() === $electionRound) {
                return true;
            }
        }

        return false;
    }

    public function addElectionRoundResult(ElectionRoundResult $result): void
    {
        if (!$this->electionRoundResults->contains($result)) {
            $result->setElectionResult($this);
            $this->electionRoundResults->add($result);
        }
    }
}
