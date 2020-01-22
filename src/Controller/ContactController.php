<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Controller;

use App\Form\ReCaptchaType;
use App\Service\ConfigService;
use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactController extends AbstractController
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator) {
        $this->translator = $translator;
    }

    #[Route(path: '/contact', name: 'contact')]
    public function index(Request $request, ConfigService $config, MailerInterface $mailer, LoggerInterface $logger): Response
    {
        $hasCaptcha = $this->getParameter('google_recaptcha_site_key') && $this->getParameter('google_recaptcha_secret');

        $builder = $this->createFormBuilder(null, ['attr' => ['id' => 'contact-form']])
            ->add('name', TextType::class, [
                'constraints' => [new NotBlank(), new Length(max: 64)]
            ])
            ->add('email', EmailType::class, [
                'constraints' => [new NotBlank(), new \Symfony\Component\Validator\Constraints\Email()]
            ])
            ->add('subject', TextType::class, [
                'constraints' => [new NotBlank(), new Length(max: 256)]
            ])
            ->add('message', TextareaType::class, [
                'constraints' => [new NotBlank(), new Length(max: 4096)]
            ])
            ->add(substr(md5($config->get('name')), 0, 8), HiddenType::class, [
                'constraints' => [new Blank()]
            ]);

        if ($hasCaptcha) {
            $builder->add('send', ReCaptchaType::class, [
                'label' => '<i class="bi bi-envelope me-1"></i>Send',
                'label_html' => true,
                'attr' => [
                    'data-sitekey' => $this->getParameter('google_recaptcha_site_key'),
                    'data-action' => 'contact'
                ]
            ]);
        } else {
            $builder->add('send', SubmitType::class, ['label' => '<i class="bi bi-envelope me-1"></i>Send', 'label_html' => true]);
        }

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($hasCaptcha) {
                    if (count($errors = $this->checkRecaptcha($request)) > 0) {
                        throw new \UnexpectedValueException(implode(',', $errors));
                    }
                }

                $data = $form->getData();
                $email = (new Email())
                    ->from(Address::create($config->get('email_from', 'contact')))
                    ->to(Address::create($config->get('email_to', 'contact')))
                    ->replyTo(new Address($data['email'], $data['name']))
                    ->subject($data['subject'])
                    ->text($data['message']);
                $mailer->send($email);
                $logger->info('Email sent from contact form', ['address' => $data['email']]);
                $this->addFlash('success', $this->translator->trans('Email sent successfully'));
                return $this->redirectToRoute('contact');
            } catch(RfcComplianceException $e) {
                $logger->debug('Problem with email address', ['cause' => $e->getMessage()]);
                $this->addFlash('danger', $this->translator->trans('An email address was invalid, please check the address and try again'));
            } catch (\UnexpectedValueException $e) {
                $logger->warning('Problem with email captcha', ['cause' => $e->getMessage()]);
                $this->addFlash('danger', $this->translator->trans('There was a problem validating your client, please try again later'));
            } catch (\Throwable $e) {
                $logger->critical('Problem sending email', ['cause' => $e->getMessage()]);
                $this->addFlash('danger', $this->translator->trans('There was a problem sending your email, please try again later'));
            }
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
            'has_captcha' => $hasCaptcha,
        ]);
    }

    private function checkRecaptcha(Request $request): array
    {
        $recaptcha = new ReCaptcha($this->getParameter('google_recaptcha_secret'));
        $resp = $recaptcha->setScoreThreshold(0.5)
            ->setExpectedAction('contact')
            ->verify($request->request->get('g-recaptcha-response'), $request->getClientIp());
        if (!$resp->isSuccess()) {
            return $resp->getErrorCodes();
        }
        return [];
    }
}
