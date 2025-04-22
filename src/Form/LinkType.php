<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Form;

use Evolutive\Module\EvoLinkManager\Repository\PlacementRepository;
use Evolutive\Module\EvoLinkManager\Service\CMSService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

/**
 * Form type for link management
 */
class LinkType extends AbstractType
{
    /**
     * @param CMSService $cmsService Service for CMS operations
     * @param PlacementRepository|null $placementRepository Repository for placement operations
     */
    public function __construct(
        private readonly CMSService $cmsService,
        private readonly ?PlacementRepository $placementRepository = null,
    ) {
    }

    /**
     * Build the link form
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Name is required',
                    ]),
                ],
                'translation_domain' => 'Modules.Evolinkmanager.Admin',
            ])
            ->add('identifier', TextType::class, [
                'label' => 'Identifier',
                'required' => false,
                'help' => 'The unique identifier used in templates: {get_evo_link_by_placement identifier=\'your_identifier\'}',
                'constraints' => [
                    new Regex([
                        'pattern' => '/^[a-z0-9_]+$/',
                        'message' => 'Identifier can only contain lowercase letters, numbers and underscores',
                    ]),
                    new Length([
                        'max' => 50,
                        'maxMessage' => 'Identifier must be at most {{ limit }} characters',
                    ]),
                ],
                'translation_domain' => 'Modules.Evolinkmanager.Admin',
            ])
            ->add('link_type', ChoiceType::class, [
                'label' => 'Link Type',
                'choices' => [
                    'Custom' => 'custom',
                    'Contact' => 'contact',
                    'CMS Page' => 'cms',
                ],
                'required' => true,
                'translation_domain' => 'Modules.Evolinkmanager.Admin',
            ])
            ->add('url', TextType::class, [
                'label' => 'URL',
                'required' => false,
                'constraints' => [
                    new Url([
                        'message' => 'Please enter a valid URL',
                    ]),
                ],
                'help' => 'For custom and contact link types',
                'translation_domain' => 'Modules.Evolinkmanager.Admin',
            ])
            ->add('id_cms', ChoiceType::class, [
                'label' => 'CMS Page',
                'choices' => $this->cmsService->getCMSPages(),
                'required' => false,
                'help' => 'For CMS link type',
                'translation_domain' => 'Modules.Evolinkmanager.Admin',
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'required' => true,
                'constraints' => [
                    new GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'Position must be a positive number',
                    ]),
                ],
                'translation_domain' => 'Modules.Evolinkmanager.Admin',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'translation_domain' => 'Modules.Evolinkmanager.Admin',
            ]);

        // Add event listener to handle form fields based on link type
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (!isset($data['link_type'])) {
                return;
            }

            switch ($data['link_type']) {
                case 'custom':
                    // For custom links, URL is required
                    $form->add('url', TextType::class, [
                        'label' => 'URL',
                        'required' => true,
                        'constraints' => [
                            new NotBlank([
                                'message' => 'URL is required for custom links',
                            ]),
                            new Url([
                                'message' => 'Please enter a valid URL',
                            ]),
                        ],
                        'translation_domain' => 'Modules.Evolinkmanager.Admin',
                    ]);
                    break;

                case 'contact':
                    // For contact links, URL is required
                    $form->add('url', TextType::class, [
                        'label' => 'URL',
                        'required' => true,
                        'constraints' => [
                            new NotBlank([
                                'message' => 'URL is required for contact links',
                            ]),
                            new Url([
                                'message' => 'Please enter a valid URL',
                            ]),
                        ],
                        'translation_domain' => 'Modules.Evolinkmanager.Admin',
                    ]);
                    break;

                case 'cms':
                    // For CMS links, id_cms is required
                    $form->add('id_cms', ChoiceType::class, [
                        'label' => 'CMS Page',
                        'choices' => $this->cmsService->getCMSPages(),
                        'required' => true,
                        'constraints' => [
                            new NotBlank([
                                'message' => 'CMS Page is required for CMS links',
                            ]),
                        ],
                        'translation_domain' => 'Modules.Evolinkmanager.Admin',
                    ]);
                    break;
            }
        });
    }

    /**
     * Configure options for this form type
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'Modules.Evolinkmanager.Admin',
        ]);
    }
}
