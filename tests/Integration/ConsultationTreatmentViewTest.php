<?php

declare(strict_types=1);

namespace Tests\Integration;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class ConsultationTreatmentViewTest extends TestCase
{
    private function viewElements(): DOMXPath
    {
        $source = file_get_contents(
            BASE_PATH . '/app/Views/consultas/show.php'
        );
        $this->assertNotFalse($source);

        $html = preg_replace('/<\?php.*?\?>|<\?=.*?\?>/s', '', $source);
        $this->assertNotNull($html);

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $this->assertTrue(
                $document->loadHTML($html, LIBXML_NONET)
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    public function testTreatmentButtonTargetsExistingModal(): void
    {
        $elements = $this->viewElements();
        $buttons = $elements->query(
            '//button[@data-modal-open="treatment-create"]'
        );
        $this->assertCount(1, $buttons);
        $this->assertCount(
            1,
            $elements->query('//div[@id="treatment-create"]')
        );
    }

    public function testMedicationControlsMatchJavascriptSelectors(): void
    {
        $elements = $this->viewElements();
        $this->assertCount(
            1,
            $elements->query(
                '//button[@id="add-treatment-medication"][@data-add-medication]'
            )
        );
        $this->assertCount(
            1,
            $elements->query('//div[@id="treatment-medications"]')
        );
        $this->assertCount(
            1,
            $elements->query(
                '//form[@id="treatment-form"]//select[@name="tipo_tratamiento_id"]'
            )
        );
    }

    public function testApplicationButtonAndFieldsMatchJavascriptAndService(): void
    {
        $elements = $this->viewElements();
        $buttons = $elements->query('//button[@data-medication-apply]');
        $this->assertCount(1, $buttons);
        $button = $buttons->item(0);
        $this->assertInstanceOf(DOMElement::class, $button);
        $this->assertTrue($button->hasAttribute('data-medication-id'));
        $this->assertSame(
            'medication-apply',
            $button->getAttribute('data-modal-open')
        );
        $this->assertCount(
            1,
            $elements->query('//div[@id="medication-apply"]')
        );
        $this->assertCount(
            1,
            $elements->query(
                '//form[@id="medication-apply-form"]//input'
                . '[@id="applied-quantity"][@name="cantidad_aplicada"]'
            )
        );
    }
}
