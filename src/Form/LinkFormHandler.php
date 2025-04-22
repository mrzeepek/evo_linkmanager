<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Form;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LinkFormHandler
{
    /**
     * @var int|null
     */
    private ?int $linkId = null;

    /**
     * @param FormFactoryInterface $formFactory Factory for form creation
     * @param LinkFormDataProvider $formDataProvider Provider for form data
     * @param TranslatorInterface $translator Translation service
     */
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly LinkFormDataProvider $formDataProvider,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Get the link form
     *
     * @param int|null $linkId
     *
     * @return FormInterface
     */
    public function getForm(?int $linkId = null): FormInterface
    {
        $this->linkId = $linkId;
        $data = $this->formDataProvider->getData($linkId);

        // Store the ID in the form data to ensure it's preserved
        if ($linkId !== null) {
            $data['id_link'] = $linkId;
        }

        \PrestaShopLogger::addLog(
            'Getting form data with linkId: ' . ($linkId ?? 'null') . ', identifier: ' . ($data['identifier'] ?? 'none'),
            1,
            null,
            'EvoLinkManager',
            0,
            true
        );

        $formBuilder = $this->formFactory->createBuilder(LinkType::class, $data);

        return $formBuilder->getForm();
    }

    /**
     * Save the link form
     *
     * @param array $data
     *
     * @return array Array of errors, empty if no errors
     */
    public function save(array $data): array
    {
        $errors = [];

        try {
            // Récupérer l'ID explicitement des données du formulaire si disponible
            $effectiveLinkId = $this->linkId;
            if (isset($data['id_link']) && $data['id_link'] > 0) {
                $effectiveLinkId = (int) $data['id_link'];
            }

            \PrestaShopLogger::addLog(
                'Saving link with data: ' . json_encode(array_merge(['linkId' => $effectiveLinkId], array_filter($data))),
                1,
                null,
                'EvoLinkManager',
                0,
                true
            );

            $this->formDataProvider->saveData($data, $effectiveLinkId);
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error saving link: ' . $e->getMessage(),
                3,
                null,
                'EvoLinkManager',
                0,
                true
            );

            $errors[] = $this->translator->trans(
                'Error saving link: %message%',
                [
                    '%message%' => $e->getMessage(),
                ],
                'Modules.Evolinkmanager.Admin'
            );
        }

        return $errors;
    }
}
