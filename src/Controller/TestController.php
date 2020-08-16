<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Repository\AuthorRepository;
use App\Repository\LocationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    // CRUD : Create Read Update Delete (back ?)
    // BREAD : Browse Read Edit Add Delete (front ?)

    /**
     * @Route("/", name="home")
     */
    public function list()
    {
        // On récupère le repository de l'entité Post
        $postRepository = $this->getDoctrine()->getRepository('App\Entity\Post');
        // Depuis ce dépôt, on accède aux posts demandés
        $posts = $postRepository->findAll();

        dump($posts);

        return $this->render('test/list.html.twig', [
            'posts' => $posts,
        ]);
    }

    /**
     * Affiche le post et ses infos
     * Affiche le form et traite le form d'ajout de commentaire
     * On récupère Request
     * Pour récupérer les infos postées
     * Dans le but de créer un nouveau comm pour l'associer au Post et le sauver
     *
     * @Route("/post/show/{id<\d+>}", name="post_show", methods={"GET", "POST"})
     */
    public function postShow($id, Request $request)
    {
        // On récupère le repository de l'entité Post
        $postRepository = $this->getDoctrine()->getRepository(Post::class);
        // Depuis ce dépôt, on accède aux post demandé
        $post = $postRepository->find($id);

        // 404 ?
        if ($post === null) {
            // 404 ?
            throw $this->createNotFoundException('Ce post n\'existe pas');
        }

        // Le form a-t-il été posté ?
        // Note : bientôt on verra une méthode de validation du form/de l'entité
        if ($request->isMethod('POST')) {
            // On récupère les données de la requête
            $username = $request->request->get('username');
            $content = $request->request->get('content');
            // On crée un comm
            $comm = new Comment();
            $comm->setUsername($username);
            $comm->setBody($content);
            $comm->setCreatedAt(new \DateTime());
            // On associe le post via l'objet $post (et non pas son id ;))
            $comm->setPost($post);
            // On le persiste avec le Manager de Doctrine
            $em = $this->getDoctrine()->getManager();
            $em->persist($comm);
            // On flush ! Sinon ça marche pas ;)
            $em->flush();
            // On redirige
            return $this->redirectToRoute('post_show', ['id' => $id]);
        }

        // A cause de lazy loading, ici les commentaires ne sont pas chargés
        dump($post);

        return $this->render('test/show.html.twig', [
            'post' => $post,
        ]);
    }

    /**
     * @Route("/post/edit/{id<\d+>}", name="post_edit")
     */
    public function postEdit($id)
    {
        // Pour mettre à jour un enregistrement BDD
        // 1. On récupère le repository de l'entité Post
        $postRepository = $this->getDoctrine()->getRepository(Post::class);
        // Depuis ce dépôt, on accède aux post demandé
        $post = $postRepository->find($id);

        // On le modifie (n'importe quelle de ses propriétés)
        // par ex. updatedAt...
        $post->setUpdatedAt(new \DateTime());

        // On passe par le Manager de Doctrine
        $entityManager = $this->getDoctrine()->getManager();
        // Pas besoin de persist() car objet connu de Doctrine
        // On flush()
        $entityManager->flush();

        // On redirige vers la home
        return $this->redirectToRoute('home');
    }
    
    /**
     * @Route("/post/add", name="post_add")
     */
    public function postAdd(LocationRepository $locationRepository, AuthorRepository $authorRepository)
    {
        // On crée un nouvel article
        $post = new Post();
        // On renseigne ses propriétés
        $post->setTitle('Un super titre');
        $post->setBody('Le corps de l\'article...');
        $post->setNbLikes(0);
        $post->setCreatedAt(new \DateTime());

        // Admettons que l'on poste depuis la cuisine
        // On va chercher l'objet cuisine
        $location = $locationRepository->find(2);
        // On l'associe à l'objet $post
        $post->setLocation($location);

        // Allons chercher l'auteur dont l'id 1
        $nicolasC = $authorRepository->find(1);
        // On l'associe au post
        $post->setAuthor($nicolasC);

        // Ajoutons des commentaires
        $com1 = new Comment();
        $com1->setUsername('jc')
            ->setBody('Lorem ipsum')
            ->setCreatedAt(new \DateTime());
        // Association du post via la relation Comment => Post
        // ->setPost($post);
        
        $com2 = new Comment();
        $com2->setUsername('dimitri')
            ->setBody('On chaine !')
            ->setCreatedAt(new \DateTime());
        // Association du post via la relation Comment => Post
        // ->setPost($post);

        // Ou association via la relation Post => Comment
        // Si on souhaite que Doctrine retrouve les commentaires
        // à travers la relation (et le cas échéant configurer le cascade "persist")
        // voir dans la relation $post->comments
        $post->addComment($com1);
        $post->addComment($com2);

        dump($post);

        // On passe par le Manager de Doctrine
        $entityManager = $this->getDoctrine()->getManager();
        // 1. On demande au Manager de "prendre en charge" notre objet
        // Si pas de cascade persist, on peut persister les nouveaux objets
        // directement depuis le Manager
        // $entityManager->persist($com1);
        // $entityManager->persist($com2);
        $entityManager->persist($post);
        // 2. On exécute les requêtes SQL associées
        $entityManager->flush();

        dump($post);

        return new Response('Article ajouté #'.$post->getId());
    }

    /**
     * @Route("/location/{id}", name="location_show")
     */
    public function locationShow($id, LocationRepository $locationRepository)
    {
        $location = $locationRepository->find($id);

        // On passe par la relation inverse Location => Post
        foreach ($location->getPosts() as $post) {
            dump($post);
        }

        // Alternative sans relation bidirectionnelle
        // On passe par le repository de Post
        $postRepository = $this->getDoctrine()->getRepository(Post::class);
        // Va chercher tous les posts dont la propriété $location
        // est l'objet locatoin récupéré
        // findBy prend 2 arguments : un tableau de propriétés + valeurs
        // un ORDER BY
        $posts = $postRepository->findBy(
            ['location' => $location],
            ['createdAt' => 'DESC']
        );
        dump($posts);

        return new Response('Liste des posts</body>');
    }
}
