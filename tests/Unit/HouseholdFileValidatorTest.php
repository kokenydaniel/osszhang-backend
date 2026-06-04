<?php

namespace Tests\Unit;

use App\Support\HouseholdFileValidator;
use Tests\TestCase;

class HouseholdFileValidatorTest extends TestCase
{
    public function test_accepts_pdf_with_eof_trailer(): void
    {
        $pdf = '%PDF-1.4'."\n".str_repeat('x', 200)."\n%%EOF\n";

        $this->assertTrue(HouseholdFileValidator::isValidPdf($pdf));
        $this->assertTrue(HouseholdFileValidator::isValidPayload($pdf, 'application/pdf', 'szerzodes.pdf'));
    }

    public function test_rejects_truncated_pdf_header_only(): void
    {
        $truncated = '%PDF-1.4 only header';

        $this->assertFalse(HouseholdFileValidator::isValidPdf($truncated));
    }
}
