<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\Tag;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @Route("/article")
 */
class ArticleController extends AbstractController
{
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
       $this->security = $security;
    }

    /**
     * @Route("/", name="article_index", methods={"GET"})
     */
    public function index(Request $request, ArticleRepository $articleRepository): Response
    {
        $tagsRepository = $this->getDoctrine()->getRepository(Tag::class);

        // The index route is also used to search for articles by their tag.
        // If there is no tag GET request, then show all articles.
        $articles = $articleRepository->findAll();
        $form = $request->query->get('form');
        if ($form !== NULL) {
            // Query contains the tag the user is searching for.
            $query = \key_exists('query', $form) ? $form['query'] : '';
            if ($query !== '') {
                $tag = $tagsRepository->findOneBy(['name' => $form['query']]);
                $articles = !is_null($tag) ? $tag->getArticles() : [];
            }
        }

        $searchData = ['query' => ''];
        $searchForm = $this->createFormBuilder($searchData)
            ->add('query', TextType::class, ['label' => 'Search for an article by tag'])
            ->add('search', SubmitType::class)
            ->setAction($this->generateUrl('article_index'))
            ->setMethod('GET')
            ->getForm();
        
        return $this->render('article/index.html.twig', [
            'articles' => $articles,
            'search_form' => $searchForm->createView()
        ]);
    }

    /**
     * @Route("/new", name="article_new", methods={"GET","POST"})
     */
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new Exception('You are not logged in.');
        }

        $tagsRepository = $this->getDoctrine()->getRepository(\App\Entity\Tag::class);
        $tags = $tagsRepository->findAll();

        $article = new Article();
        $form = $this->createFormBuilder($article)
            ->add('title', TextType::class)
            ->add('perex', TextType::class)
            ->add('introduction_image', FileType::class, [
                'label' => 'Introduction image',
                'mapped' => false,
                'required' => false
            ])
            ->add('body', TextType::class)
            ->add('tags', ChoiceType::class, [
                'multiple' => true,
                'mapped' => false,
                'choices' => $tags,
                'choice_label' => 'name',
                'choice_value' => 'id'
            ])
            ->add('save', SubmitType::class, ['label' => 'Publish'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($slugger->slug($article->getTitle()));
            $article->setCreatedAt(new \DateTime('NOW'));
            $article->setUpdatedAt(new \DateTime('NOW'));

            $article->setAuthor($user);

            // Set the article's tags.
            $tagIds = $request->request->get('form')['tags'];
            foreach ($tagIds as $tagId) {
                $article->addTag($tagsRepository->find($tagId));
            }

            // Save and set the introduction image.
            $imgFile = $form->get('introduction_image')->getData();
            if ($imgFile) {
                $originalFilename = pathinfo($imgFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imgFile->guessExtension();
                // Move the file to the public images directory.
                $imgFile->move(
                    // The public images directory.
                    $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR  . 'public' . DIRECTORY_SEPARATOR  . 'images',
                    $newFilename
                );
                
                $article->setIntroductionImage('images' . DIRECTORY_SEPARATOR . $newFilename);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($article);
            $entityManager->flush();

            return $this->redirectToRoute('article_index');
        }

        return $this->render('article/new.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{slug}", name="article_show", methods={"GET", "POST"})
     */
    public function show(Article $article, Request $request): Response
    {
        $user = $this->security->getUser();

        // Create the comments form.
        $comment = new Comment();
        $commentsForm = $this->createFormBuilder($comment)
            ->add('body', TextType::class, ['label' => 'Comment'])
            ->add('save', SubmitType::class, ['label' => 'Post'])
            ->getForm();
        $commentsForm->handleRequest($request);
        
        // Post submitted comments.
        if ($commentsForm->isSubmitted() && $commentsForm->isValid()) {
            if ($user) {
                $comment->setAuthor($user);
            }
            $comment->setArticle($article);
            $comment->setCreatedOn(new \DateTime('NOW'));
            $comment->setUpdatedOn(new \DateTime('NOW'));

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($comment);
            $entityManager->flush();
        }

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'comment_form' => $commentsForm->createView()
        ]);
    }

    /**
     * @Route("/{id}", name="article_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Article $article): Response
    {
        if ($this->isCsrfTokenValid('delete'.$article->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($article);
            $entityManager->flush();
        }

        return $this->redirectToRoute('article_index');
    }
}
