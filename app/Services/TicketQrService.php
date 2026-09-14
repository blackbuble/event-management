<?php

namespace App\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Renders ticket QR codes. The QR payload is the ticket_code, so any standard
 * scanner can read and hand it to the check-in flow.
 */
class TicketQrService
{
    public function png(string $data, int $size = 320): string
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return (new PngWriter)->write($qrCode)->getString();
    }

    public function dataUri(string $data, int $size = 320): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($data, $size));
    }
}
