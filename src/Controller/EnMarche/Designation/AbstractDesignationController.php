<?php

namespace App\Controller\EnMarche\Designation;

use App\Entity\Committee;
use App\Entity\VotingPlatform\Election;
use App\Entity\VotingPlatform\ElectionResult\ElectionPoolResult;
use App\Repository\VotingPlatform\ElectionRepository;
use App\Repository\VotingPlatform\VoteResultRepository;
use App\Repository\VotingPlatform\VoterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

abstract class AbstractDesignationController extends AbstractController
{
    private $electionRepository;

    public function __construct(ElectionRepository $electionRepository)
    {
        $this->electionRepository = $electionRepository;
    }

    /**
     * @Route("", name="_list", methods={"GET"})
     */
    public function listDesignationsAction(Request $request, Committee $committee): Response
    {
        return $this->renderTemplate('designation/list.html.twig', $request, [
            'committee' => $committee,
            'elections' => $this->electionRepository->getAllAggregatedDataForCommittee($committee),
        ]);
    }

    /**
     * @Route("/{uuid}", name="_dashboard", methods={"GET"})
     */
    public function dashboardAction(Request $request, Committee $committee, Election $election): Response
    {
        return $this->renderTemplate('designation/dashboard.html.twig', $request, [
            'committee' => $committee,
            'election' => $election,
            'election_stats' => $this->electionRepository->getSingleAggregatedData($election),
        ]);
    }

    /**
     * @Route("/{uuid}/liste-emargement", name="_voters_list", methods={"GET"})
     */
    public function listVotersAction(
        Request $request,
        Committee $committee,
        Election $election,
        VoterRepository $voterRepository
    ): Response {
        if ($election->isVotePeriodActive()) {
            return $this->redirectToSpaceRoute('dashboard', $committee, $election);
        }

        return $this->renderTemplate('designation/voters_list.html.twig', $request, [
            'committee' => $committee,
            'election' => $election,
            'election_stats' => $this->electionRepository->getSingleAggregatedData($election),
            'voters' => $voterRepository->findForElection($election),
        ]);
    }

    /**
     * @Route("/{uuid}/resultats", name="_results", methods={"GET"})
     */
    public function showResultsAction(Request $request, Committee $committee, Election $election): Response
    {
        if ($election->isVotePeriodActive()) {
            return $this->redirectToSpaceRoute('dashboard', $committee, $election);
        }

        if (!$election->hasResult()) {
            $this->addFlash('info', 'Les résultats ne sont pas encore prêts.');

            return $this->redirectToSpaceRoute('dashboard', $committee, $election);
        }

        return $this->renderTemplate('designation/results.html.twig', $request, [
            'committee' => $committee,
            'election' => $election,
            'election_stats' => $this->electionRepository->getSingleAggregatedData($election),
            'election_pool_result' => current(array_filter(
                $election->getElectionResult()->getElectedPoolResults(),
                function (ElectionPoolResult $poolResult) use ($request) {
                    return $poolResult->getElectionPool()->getTitle() === ($request->query->has('femme') ? 'Femme' : 'Homme');
                }
            )),
        ]);
    }

    /**
     * @Route("/{uuid}/bulletins", name="_votes", methods={"GET"})
     */
    public function listVotesAction(
        Request $request,
        Committee $committee,
        Election $election,
        VoteResultRepository $voteResultRepository
    ): Response {
        if ($election->isVotePeriodActive()) {
            return $this->redirectToSpaceRoute('dashboard', $committee, $election);
        }

        return $this->renderTemplate('designation/votes_list.html.twig', $request, [
            'committee' => $committee,
            'election' => $election,
            'election_stats' => $this->electionRepository->getSingleAggregatedData($election),
            'votes' => $voteResultRepository->getResultsForElection($election),
        ]);
    }

    abstract protected function getSpaceType(): string;

    protected function renderTemplate(string $template, Request $request, array $parameters = []): Response
    {
        return $this->render($template, array_merge(
            $parameters,
            [
                'base_template' => sprintf('designation/_base_%s.html.twig', $messageType = $this->getSpaceType()),
                'space_type' => $messageType,
                'route_params' => $this->getRouteParameters($request),
            ]
        ));
    }

    protected function redirectToSpaceRoute(
        string $subName,
        Committee $committee,
        Election $election,
        array $parameters = []
    ): Response {
        return $this->redirectToRoute("app_{$this->getSpaceType()}_designations_${subName}", array_merge([
            'committee_slug' => $committee->getSlug(),
            'uuid' => $election->getUuid()->toString(),
        ], $parameters));
    }

    protected function getRouteParameters(Request $request): array
    {
        return [
            'committee_slug' => $request->attributes->get('committee_slug'),
        ];
    }
}
