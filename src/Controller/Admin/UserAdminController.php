<?php

namespace App\Controller\Admin;

use App\Entity\Campus;
use App\Entity\User;
use App\Entity\UsersCsv;
use App\Form\UserAdminType;
use App\Form\UsersCsvType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use League\Csv\Statement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserAdminController extends AbstractController
{

    private $encoder;

    /**
     * @var UserRepository
     */

    private $repository;

    public function __construct(UserRepository $repository, UserPasswordEncoderInterface $encoder)
    {
        $this->repository = $repository;
        $this->encoder = $encoder;
    }

    /**
     * @Route("/user/admin", name="user_admin")
     */
    public function index(Request $request, EntityManagerInterface $em)
    {
        $users = $this->repository->findAll();

        $csv = new UsersCsv();

        $csvForm = $this->createForm(UsersCsvType::class, $csv);

        $csvForm->handleRequest($request);

        if($csvForm->isSubmitted() && $csvForm->isValid())
        {
            $file = $csv->getFile();
            $fileName = md5(uniqid()).'.csv';
            $csv->setName($fileName);
            $file->move($this->getParameter('csv_dir'), $fileName);
            $this->processCsv($fileName, $em);

            $em->persist($csv);
            $em->flush();
            $this->addFlash('success', 'Fichier ajouté');

            return $this->redirectToRoute('user_admin');
        }

        $formview = $csvForm->createView();
        return $this->render('user_admin/index.html.twig', compact('users', 'formview'));
    }

    public function processCsv($fileName, $em)
    {

        $campusRepo = $this->getDoctrine()->getRepository(Campus::class);
        $campuses = $campusRepo->getAllCampuses();

        $csv = Reader::createFromPath($this->getParameter('kernel.project_dir').'/public/userCsvs/'.$fileName, 'r');
        $csv->setHeaderOffset(0);


        $stmt = (new Statement())
            ->offset(0)
            ->limit(25);

        $records = $stmt->process($csv);

        foreach ($records as $record)
        {
            $user = (new User())
                ->setUsername($record['username'])
                ->setFirstname($record['firstname'])
                ->setLastname($record['lastname'])
                ->setPhoneNumber($record['phone_number'])
                ->setMail($record['mail'])
                ->setPassword('1234')
                ->setAdmin(0)
                ->setActive(1)
                ->setCampus($campuses[$record['campus']]);

            $em->persist($user);
        }
    }

    /**
     * @Route("/user/admin/createnewuser", name="user_admin_create_user")
     */
    public function createNewUser(Request $request, EntityManagerInterface $em)
    {
        $user = new User();

        $newUserForm = $this->createForm(UserAdminType::class, $user);

        $newUserForm->handleRequest($request);

        if ($newUserForm->isSubmitted() && $newUserForm->isValid()) {


            $user->setPassword($this->encoder->encodePassword($user, $newUserForm->getData()->getPassword()));
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur ajouté');
            return $this->redirectToRoute('user_admin');
        }

        $formview = $newUserForm->createView();
        return $this->render('user_admin/createUser.html.twig', compact( 'formview'));
    }
}