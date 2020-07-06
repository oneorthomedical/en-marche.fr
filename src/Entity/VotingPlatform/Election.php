<?php

namespace App\Entity\VotingPlatform;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use App\Entity\EntityDesignationTrait;
use App\Entity\EntityIdentityTrait;
use App\Entity\VotingPlatform\Designation\Designation;
use App\Entity\VotingPlatform\ElectionResult\ElectionResult;
use App\VotingPlatform\Election\ElectionStatusEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VotingPlatform\ElectionRepository")
 *
 * @ORM\Table(name="voting_platform_election")
 *
 * @Algolia\Index(autoIndex=false)
 */
class Election
{
    use EntityIdentityTrait;
    use EntityDesignationTrait {
        isVotePeriodActive as isDesignationVotePeriodActive;
        getRealVoteEndDate as getDesignationRealVoteEndDate;
    }

    /**
     * @var ElectionEntity
     *
     * @ORM\OneToOne(targetEntity="App\Entity\VotingPlatform\ElectionEntity", mappedBy="election", cascade={"all"})
     */
    private $electionEntity;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $status = ElectionStatusEnum::OPEN;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $closedAt;

    /**
     * @var ElectionRound[]|Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\VotingPlatform\ElectionRound", mappedBy="election", cascade={"all"})
     */
    private $electionRounds;

    /**
     * @var ElectionPool[]|Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\VotingPlatform\ElectionPool", mappedBy="election", cascade={"all"})
     */
    private $electionPools;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $secondRoundEndDate;

    /**
     * @var ElectionResult|null
     *
     * @ORM\OneToOne(targetEntity="App\Entity\VotingPlatform\ElectionResult\ElectionResult", mappedBy="election")
     */
    private $electionResult;

    public function __construct(Designation $designation, UuidInterface $uuid = null, array $rounds = [])
    {
        $this->designation = $designation;
        $this->uuid = $uuid ?? Uuid::uuid4();

        $this->electionRounds = new ArrayCollection();
        $this->electionPools = new ArrayCollection();

        foreach ($rounds as $round) {
            $this->addElectionRound($round);
        }
    }

    public function getTitle(): string
    {
        return $this->designation->getTitle();
    }

    public function getDesignationType(): string
    {
        return $this->designation->getType();
    }

    public function getElectionEntity(): ElectionEntity
    {
        return $this->electionEntity;
    }

    public function setElectionEntity(ElectionEntity $electionEntity): void
    {
        $electionEntity->setElection($this);
        $this->electionEntity = $electionEntity;
    }

    public function getRealVoteEndDate(): \DateTime
    {
        return $this->secondRoundEndDate ? $this->secondRoundEndDate : $this->getDesignationRealVoteEndDate();
    }

    public function isVotePeriodActive(): bool
    {
        return ElectionStatusEnum::OPEN === $this->status
            && ($this->isDesignationVotePeriodActive() || $this->isSecondRoundVotePeriodActive());
    }

    public function close(): void
    {
        $this->status = ElectionStatusEnum::CLOSED;
        $this->closedAt = new \DateTime();
    }

    public function addElectionRound(ElectionRound $round): void
    {
        if (!$this->electionRounds->contains($round)) {
            $round->setElection($this);
            $this->electionRounds->add($round);
        }
    }

    public function addElectionPool(ElectionPool $pool): void
    {
        if (!$this->electionPools->contains($pool)) {
            $pool->setElection($this);
            $this->electionPools->add($pool);
        }
    }

    public function getCurrentRound(): ?ElectionRound
    {
        foreach ($this->electionRounds as $round) {
            if ($round->isActive()) {
                return $round;
            }
        }

        return null;
    }

    /**
     * @param ElectionPool[] $pools
     */
    public function startSecondRound(array $pools): void
    {
        $this->getCurrentRound()->disable();

        $this->addElectionRound($secondRound = new ElectionRound());
        $secondRound->setElectionPools($pools);

        $this->secondRoundEndDate = (clone $this->getVoteEndDate())->modify(
            sprintf('+%d days', $this->getAdditionalRoundDuration())
        );
    }

    public function isSecondRoundVotePeriodActive(): bool
    {
        return null !== $this->secondRoundEndDate && (new \DateTime()) <= $this->secondRoundEndDate;
    }

    public function getElectionResult(): ?ElectionResult
    {
        return $this->electionResult;
    }

    public function hasResult(): bool
    {
        return null !== $this->electionResult;
    }
}
