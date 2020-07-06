<?php

namespace App\Controller\EnMarche\VotingPlatform;

use App\Entity\VotingPlatform\Election;
use App\VotingPlatform\VoteResult\VoteResultAggregator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/resultats", name="app_voting_platform_results", methods={"GET"})
 */
class ResultsController extends AbstractController
{
    public function __invoke(VoteResultAggregator $resultAggregator, Election $election): Response
    {
        if (!$election->isResultPeriodActive()) {
            return $this->redirect($this->redirectManager->getRedirection($election));
        }

        return $this->renderElectionTemplate('voting_platform/results.html.twig', $election, [
            'candidate_groups' => $this->candidateGroupRepository->findForElectionRound($election->getCurrentRound()),
            'results' => dump($resultAggregator->getResults($election)),
            'pools' => $election->getCurrentRound()->getElectionPools(),
        ]);
    }
}
