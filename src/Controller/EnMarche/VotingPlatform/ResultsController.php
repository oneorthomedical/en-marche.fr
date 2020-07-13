<?php

namespace App\Controller\EnMarche\VotingPlatform;

use App\Entity\VotingPlatform\Election;
use App\Repository\VotingPlatform\VoteResultRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/resultats", name="app_voting_platform_results", methods={"GET"})
 */
class ResultsController extends AbstractController
{
    public function __invoke(VoteResultRepository $voteResultRepository, Election $election): Response
    {
        if (!$election->isResultPeriodActive()) {
            return $this->redirect($this->redirectManager->getRedirection($election));
        }

        return $this->renderElectionTemplate('voting_platform/results.html.twig', $election, [
            'vote_results' => $voteResultRepository->getResultsForElection($election),
        ]);
    }
}
