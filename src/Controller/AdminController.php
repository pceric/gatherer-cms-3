<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Category;
use App\Entity\Config;
use App\Entity\Reader;
use App\Entity\User;
use App\Form\ConfigType;
use App\Form\TagTransformer;
use App\Form\Type\JsonType;
use App\Repository\BlogRepository;
use App\Repository\CategoryRepository;
use App\Repository\ConfigRepository;
use App\Repository\ReaderRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route(path: '/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    /**
     * @param ConfigRepository $configRepo
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route(path: '/admin/settings', name: 'admin_settings')]
    public function settings(ConfigRepository $configRepo, Request $request, EntityManagerInterface $entityManager): Response
    {
        $configSchema = $this->getParameter('app.config_schema');
        $current = [];
        foreach ($configSchema as $ns => $value) {
            $one = $configRepo->findOneBy(['namespace' => $ns]);
            if ($one == null) {
                $config = new Config();
                $config->setNamespace($ns);
                $current[] = $config;
            } else {
                $current[] = $one;
            }
        }
        $fbi = $this->createFormBuilder($current);
        foreach ($configSchema as $key => $value) {
            $fbi->add(
                $key,
                CollectionType::class,
                ['entry_type' => JsonType::class, 'entry_options' => ['fields' => $value['fields']], 'attr' => ['class' => 'no-legend'], 'label' => $value['label'], 'label_attr' => ['class' => 'h3']]
            );
        }
        $fbi->add('save', SubmitType::class)->setDataMapper(new ConfigType());
        $form = $fbi->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->getData() as $datum) {
                $entityManager->persist($datum);
            }
            $entityManager->flush();

            $this->addFlash('success', 'Settings Saved!');
            return $this->redirectToRoute('admin');
        }

        return $this->render('admin/settings.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/admin/blog', name: 'admin_blog')]
    public function blogRead(BlogRepository $blogRepository, Request $request): Response
    {
        $query = $blogRepository->createQueryBuilder('b')->orderBy('b.id', 'DESC');
        $pagerfanta = new Pagerfanta(new QueryAdapter($query));
        $pagerfanta->setMaxPerPage(50);
        if ($pagerfanta->haveToPaginate()) {
            $pagerfanta->setCurrentPage((int)$request->query->get('page', 1));
        }
        return $this->render('admin/blog.html.twig', [
            'blogs' => $pagerfanta->getCurrentPageResults(),
            'pager' => $pagerfanta,
        ]);
    }

    #[Route(path: '/admin/blog/create', name: 'admin_blog_create')]
    public function blogCreate(Request $request, TagTransformer $transformer, EntityManagerInterface $em): Response
    {
        $news = new Blog();
        $news->setUser($this->getUser());
        $news->setPubdate(new \DateTime());
        return $this->blogEdit($news, $request, $transformer, $em, true);
    }

    #[Route(path: '/admin/blog/edit/{slug}', name: 'admin_blog_edit')]
    public function blogEdit(#[MapEntity(mapping: ['slug' => 'slug'])] Blog $blog, Request $request, TagTransformer $transformer, EntityManagerInterface $em, bool $isNew = false): Response
    {
        $cat = $em->getRepository(Category::class);
        $builder = $this->createFormBuilder($blog)
            ->add('title', TextType::class)
            ->add('content', TextareaType::class, ['required' => false])
            ->add('category', ChoiceType::class, ['choices' => $cat->findAll(), 'choice_value' => 'id', 'choice_label' => 'name'])
            ->add('tags', TextType::class, ['required' => false])
            ->add('pubdate', DateTimeType::class, ['label' => 'Published Date', 'widget' => 'single_text'])
            ->add('sticky', CheckboxType::class, ['required' => false, 'label_attr' => ['class' => 'checkbox-switch']])
            ->add('published', CheckboxType::class, ['required' => false, 'label_attr' => ['class' => 'checkbox-switch']])
            ->add('save', SubmitType::class, ['attr' => ['style' => 'float:left;margin-right:5px']])
            ->add('cancel', ButtonType::class, ['attr' => ['onclick' => 'window.history.back()']]);
        $builder->get('tags')->addModelTransformer($transformer);
        $form = $builder->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $news = $form->getData();
            if (!$isNew && $news->getPublished()) {
                $news->setModdate(new \DateTime());
            }

            $em->persist($blog);
            $em->flush();

            $this->addFlash('success', 'Blog Entry Saved!');
            return $this->redirectToRoute('blog_show', ['slug' => $blog->getSlug()]);
        }

        return $this->render('blog/edit.html.twig', [
            'form' => $form,
            'isNew' => $isNew,
        ]);
    }


    #[Route(path: '/admin/blog/delete/{id}', name: 'admin_blog_delete', methods: ['POST'])]
    public function blogDelete(#[MapEntity(mapping: ['id' => 'id'])] Blog $blog, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_blog_' . $blog->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $entityManager->remove($blog);
        $entityManager->flush();
        $this->addFlash('success', 'Blog Entry Deleted!');
        return $this->redirectToRoute('admin_blog');
    }

    #[Route(path: '/admin/gathered', name: 'admin_gathered')]
    public function gatheredRead(ReaderRepository $readerRepository, Request $request): Response
    {
        $query = $readerRepository->createQueryBuilder('r')->orderBy('r.pubdate', 'DESC');
        $pagerfanta = new Pagerfanta(new QueryAdapter($query));
        $pagerfanta->setMaxPerPage(50);
        if ($pagerfanta->haveToPaginate()) {
            $pagerfanta->setCurrentPage((int)$request->query->get('page', 1));
        }
        return $this->render('admin/gathered.html.twig', [
            'readers' => $pagerfanta->getCurrentPageResults(),
            'pager' => $pagerfanta,
        ]);
    }

    #[Route(path: '/admin/gathered/delete/{id}', name: 'admin_gathered_delete', methods: ['POST'])]
    public function gatheredDelete(#[MapEntity(mapping: ['id' => 'id'])] Reader $reader, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_reader_' . $reader->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $entityManager->remove($reader);
        $entityManager->flush();
        $this->addFlash('success', 'Gathered Entry Deleted!');
        return $this->redirectToRoute('admin_gathered');
    }

    #[Route(path: '/admin/reader/{id}/annotation', name: 'admin_reader_annotation', methods: ['PATCH'])]
    public function readerAnnotation(#[MapEntity(mapping: ['id' => 'id'])] Reader $reader, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $annotation = trim($data['annotation'] ?? '');
        $reader->setAnnotation($annotation ?: null);
        $em->persist($reader);
        $em->flush();
        return $this->json(['annotation' => $reader->getAnnotation()]);
    }

    #[Route(path: '/admin/category', name: 'admin_category')]
    public function categoryRead(CategoryRepository $categoryRepository): Response
    {
        return $this->render('admin/category.html.twig', [
            'categories' => $categoryRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route(path: '/admin/category/create', name: 'admin_category_create', methods: ['POST'])]
    public function categoryCreate(Request $request, CategoryRepository $categoryRepository, EntityManagerInterface $em): Response
    {
        $name = trim($request->request->get('name', ''));
        if ($name === '') {
            $this->addFlash('danger', 'Category name cannot be empty.');
            return $this->redirectToRoute('admin_category');
        }

        if ($categoryRepository->findOneBy(['name' => $name])) {
            $this->addFlash('danger', 'A category with that name already exists.');
            return $this->redirectToRoute('admin_category');
        }

        $slug = substr(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name, ' '))), 0, 32);
        $slug = trim($slug, '-');

        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);
        $categoryRepository->add($category, true);

        $this->addFlash('success', "Category \"{$name}\" created.");
        return $this->redirectToRoute('admin_category');
    }

    #[Route(path: '/admin/category/delete/{id}', name: 'admin_category_delete')]
    public function categoryDelete(#[MapEntity(mapping: ['id' => 'id'])] Category $category, EntityManagerInterface $em): Response
    {
        if ($category->getName() === 'General') {
            $this->addFlash('danger', 'The General category cannot be deleted.');
            return $this->redirectToRoute('admin_category');
        }

        $name = $category->getName();
        try {
            $em->remove($category);
            $em->flush();
            $this->addFlash('success', "Category \"{$name}\" deleted.");
        } catch (ForeignKeyConstraintViolationException) {
            $em->clear();
            $this->addFlash('danger', "Cannot delete \"{$name}\" — it still has blog posts assigned to it.");
        }
        return $this->redirectToRoute('admin_category');
    }

    #[Route(path: '/admin/users', name: 'admin_users')]
    public function userRead(UserRepository $userRepository): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $userRepository->findBy([], ['id' => 'ASC']),
        ]);
    }

    #[Route(path: '/admin/users/create', name: 'admin_user_create')]
    public function userCreate(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        $user = new User();
        return $this->userEdit($user, $request, $passwordHasher, $em, true);
    }

    #[Route(path: '/admin/users/edit/{id}', name: 'admin_user_edit')]
    public function userEdit(#[MapEntity(mapping: ['id' => 'id'])] User $user, Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, bool $isNew = false): Response
    {
        $currentRole = in_array('ROLE_ADMIN', $user->getRoles()) ? 'ROLE_ADMIN' : 'ROLE_EDITOR';

        $formData = [
            'username'    => $isNew ? '' : $user->getUsername(),
            'displayname' => $user->getDisplayname() ?? '',
            'role'        => $currentRole,
            'password'    => null,
        ];

        $roleProtected = !$isNew && $user->getId() === 1;

        $form = $this->createFormBuilder($formData)
            ->add('username', TextType::class, ['disabled' => !$isNew])
            ->add('displayname', TextType::class, ['label' => 'Display Name'])
            ->add('role', ChoiceType::class, [
                'choices'  => ['Admin' => 'ROLE_ADMIN', 'Editor' => 'ROLE_EDITOR'],
                'disabled' => $roleProtected,
            ])
            ->add('password', RepeatedType::class, [
                'type'             => PasswordType::class,
                'required'         => $isNew,
                'invalid_message'  => 'The password fields must match.',
                'options'          => ['attr' => ['autocomplete' => 'new-password']],
                'first_options'    => [
                    'label' => $isNew ? 'Password' : 'New Password',
                    'help'  => $isNew ? null : 'Leave blank to keep the current password.',
                ],
                'second_options'   => ['label' => 'Repeat Password'],
            ])
            ->add('save', SubmitType::class)
            ->add('cancel', ButtonType::class, ['attr' => ['onclick' => "location.href='" . $this->generateUrl('admin_users') . "'"]])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if ($isNew) {
                $user->setUsername($data['username']);
            }
            $user->setDisplayname($data['displayname']);
            if (!$roleProtected) {
                $user->setRoles([$data['role']]);
            }

            if (!empty($data['password'])) {
                $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', $isNew ? 'User created.' : 'User updated.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_edit.html.twig', [
            'form'  => $form,
            'user'  => $user,
            'isNew' => $isNew,
        ]);
    }

    #[Route(path: '/admin/users/delete/{id}', name: 'admin_user_delete', methods: ['POST'])]
    public function userDelete(#[MapEntity(mapping: ['id' => 'id'])] User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($user->getId() === 1) {
            $this->addFlash('danger', 'The original admin user cannot be deleted.');
            return $this->redirectToRoute('admin_users');
        }
        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'User deleted.');
        return $this->redirectToRoute('admin_users');
    }
}
