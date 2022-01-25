<?php

namespace App\Service;

use App\Entity\Mentoring;
use App\Entity\Student;
use App\Repository\MentorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class MatchManager
{
    private MentorRepository $mentorRepository;
    private EntityManagerInterface $entityManager;
    private MailerManager $mailerManager;

    public function __construct(
        MentorRepository $mentorRepository,
        MailerManager $mailerManager,
        EntityManagerInterface $entityManager
    ) {
        $this->mentorRepository = $mentorRepository;
        $this->entityManager = $entityManager;
        $this->mailerManager = $mailerManager;
    }
    /**
     * return an array of mentors with no active mentoring, matching with one of the studentTopics, by priority :
     * studentTopic1 > studentTopic2 > studentTopic3
     */
    public function matchByTopic(Student $studentToMatch): array
    {
        $mentors = [];
        if ($studentToMatch->getTopic() !== null) {
            //try matching by topic 1
            $mentors = $this->mentorRepository->findMentorsByTopic($studentToMatch->getTopic()->getTopic1());
        }
        //if no match, try matching by topic 2
        if (
            empty($mentors)
            && $studentToMatch->getTopic() !== null
            && $studentToMatch->getTopic()->getTopic2() !== null
        ) {
            $mentors = $this->mentorRepository->findMentorsByTopic($studentToMatch->getTopic()->getTopic2());
        }
        //if no match, try matching by topic 3
        if (
            empty($mentors)
            && $studentToMatch->getTopic() !== null
            && $studentToMatch->getTopic()->getTopic3() !== null
        ) {
            $mentors = $this->mentorRepository->findMentorsByTopic($studentToMatch->getTopic()->getTopic3());
        }
        //if no match by topic 1, 2 or 3 stop trying
        if (empty($mentors)) {
            throw new Exception('There is no mentor with the same Topic');
        }
        return $mentors;
    }
    /**
     * Match a student and a mentor by priority topics and same professionnal sector
     * if there is no mentor with same professional sector, the student will match only by topics
     */
    public function match(Student $studentToMatch): void
    {
        $mentorsBySector = [];

        //check if student has already a mentor
        if ($studentToMatch->getMentoring() === null) {
            $matchingMentors = $this->matchByTopic($studentToMatch);

            //try to find a Mentor with same professionalSector than student to match
            foreach ($matchingMentors as $matchingMentor) {
                if ($matchingMentor->getProfessionalSector() === $studentToMatch->getProfessionalSector()) {
                    $mentorsBySector[] = $matchingMentor;
                }
            }
            // if no match by professional sector, get the first mentor of the list
            if (empty($mentorsBySector)) {
                $matchingMentor = $matchingMentors[0];
            }
            //there is a match by sector, get the first mentor of the list
            $matchingMentor = $mentorsBySector[0];

            //creation of a new mentoring relation
            $mentoring = new Mentoring();
            $mentoring->setStudent($studentToMatch);
            $mentoring->setMentor($matchingMentor);
            $this->entityManager->flush();
            //sending mentoring proposition to student
            $this->mailerManager->sendProposal($studentToMatch);
        } else {
            throw new Exception("This student already has an active mentoring");
        }
    }
}
