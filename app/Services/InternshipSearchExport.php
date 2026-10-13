<?php

namespace App\Services;

use App\Models\InternshipContact;
use App\Models\InternshipRemark;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Export du suivi de recherche de stage (contacts + remarques) en PDF ou Excel. */
class InternshipSearchExport
{
    public const HEADINGS = [
        'Entreprise / organisme', 'Adresse', 'Interlocuteur', 'Date de contact', 'Moyen de contact',
        'Résultat', 'Date de relance', 'Moyen de relance', 'Notes',
    ];

    /** Couleurs communes aux deux formats. */
    protected const HEADER_BG = '1E293B';
    protected const ZEBRA_BG = 'F8FAFC';
    protected const BORDER = 'CBD5E1';
    protected const OVERDUE = 'B91C1C';

    /** Ligne d'en-tête du tableau des contacts (titre et synthèse au-dessus). */
    protected const HEADER_ROW = 4;

    /**
     * Indicateurs repris en tête des exports.
     *
     * @param  Collection<int, InternshipContact>  $contacts
     * @return array<string, int|string>
     */
    public function summary(Collection $contacts): array
    {
        $answered = $contacts->whereIn('status', ['accepted', 'refused'])->count();

        return [
            'Entreprises contactées' => $contacts->count(),
            'Réponses attendues'     => $contacts->filter->isAwaiting()->count(),
            'Relances à faire'       => $contacts->filter->followUpDue()->count(),
            'Acceptées'              => $contacts->where('status', 'accepted')->count(),
            'Refusées'               => $contacts->where('status', 'refused')->count(),
            'Taux de réponse'        => ($contacts->count() ? (int) round($answered / $contacts->count() * 100) : 0).' %',
        ];
    }

    /**
     * @param  Collection<int, InternshipContact>  $contacts
     * @param  Collection<int, InternshipRemark>  $remarks
     */
    public function pdf(Collection $contacts, Collection $remarks): Response
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('admin.internship.pdf', [
            'contacts' => $contacts,
            'remarks'  => $remarks,
            'summary'  => $this->summary($contacts),
            'headings' => self::HEADINGS,
        ])->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // Pied de page : date d'édition et pagination
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(34, $canvas->get_height() - 24, 'Recherche de stage — édité le '.now()->format('d/m/Y à H:i'), $font, 7, [0.39, 0.45, 0.55]);
        $canvas->page_text($canvas->get_width() - 90, $canvas->get_height() - 24, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.39, 0.45, 0.55]);

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename('pdf').'"',
            'Cache-Control'       => 'no-store, private',
        ]);
    }

    /**
     * @param  Collection<int, InternshipContact>  $contacts
     * @param  Collection<int, InternshipRemark>  $remarks
     */
    public function excel(Collection $contacts, Collection $remarks): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle('Recherche de stage')->setCreator(config('app.name'));
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->contactsSheet($spreadsheet->getActiveSheet(), $contacts);
        $this->remarksSheet($spreadsheet->createSheet(), $remarks);
        $this->summarySheet($spreadsheet->createSheet(), $contacts);
        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->filename('xlsx'), [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @param  Collection<int, InternshipContact>  $contacts */
    protected function contactsSheet(Worksheet $sheet, Collection $contacts): void
    {
        $sheet->setTitle('Contacts');
        $this->title($sheet, 'Suivi de la recherche de stage', 'I', $contacts->count().' entreprise(s) contactée(s) — édité le '.now()->format('d/m/Y'));

        $header = self::HEADER_ROW;
        $sheet->fromArray(self::HEADINGS, null, "A{$header}");
        $this->styleHeader($sheet, "A{$header}:I{$header}");

        foreach ($contacts->values() as $i => $contact) {
            $row = $header + 1 + $i;
            $values = [
                $contact->company, $contact->address, $contact->contact_name,
                ExcelDate::PHPToExcel($contact->contacted_on), $contact->methodLabel(), $contact->statusLabel(),
                $contact->isAwaiting() && $contact->follow_up_on ? ExcelDate::PHPToExcel($contact->follow_up_on) : null,
                $contact->isAwaiting() ? $contact->followUpMethodLabel() : null, $contact->notes,
            ];
            foreach ($values as $col => $value) {
                $cell = $sheet->getCell([$col + 1, $row]);
                in_array($col, [3, 6], true) || $value === null
                    ? $cell->setValue($value)
                    : $cell->setValueExplicit($value, DataType::TYPE_STRING); // pas de formule injectée
            }

            if ($i % 2) {
                $this->fill($sheet, "A{$row}:I{$row}", self::ZEBRA_BG);
            }

            [, $background, $color] = $contact->statusStyle();
            $this->fill($sheet, "F{$row}", $background);
            $sheet->getStyle("F{$row}")->getFont()->setBold(true)->getColor()->setRGB($color);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            if ($contact->followUpDue()) {
                $sheet->getStyle("G{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::OVERDUE);
            }
        }

        $last = $header + max(1, $contacts->count());
        $sheet->getStyle("D".($header + 1).":D{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle("G".($header + 1).":G{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle("A".($header + 1).":I{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle("D".($header + 1).":H{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->borders($sheet, "A{$header}:I{$last}");

        foreach (['A' => 28, 'B' => 30, 'C' => 20, 'D' => 13, 'E' => 14, 'F' => 22, 'G' => 13, 'H' => 14, 'I' => 55] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('B'.($header + 1));
        $sheet->setAutoFilter("A{$header}:I{$last}");
        $this->printSetup($sheet, $header);
    }

    /** @param  Collection<int, InternshipRemark>  $remarks */
    protected function remarksSheet(Worksheet $sheet, Collection $remarks): void
    {
        $sheet->setTitle('Remarques');
        $this->title($sheet, 'Remarques sur la recherche', 'B', $remarks->count().' remarque(s), de la plus récente à la plus ancienne');

        $header = self::HEADER_ROW;
        $sheet->fromArray(['Date', 'Remarque'], null, "A{$header}");
        $this->styleHeader($sheet, "A{$header}:B{$header}");

        foreach ($remarks->values() as $i => $remark) {
            $row = $header + 1 + $i;
            $sheet->setCellValue([1, $row], ExcelDate::PHPToExcel($remark->noted_on));
            $sheet->setCellValueExplicit([2, $row], $remark->body, DataType::TYPE_STRING);
            if ($i % 2) {
                $this->fill($sheet, "A{$row}:B{$row}", self::ZEBRA_BG);
            }
        }

        $last = $header + max(1, $remarks->count());
        $sheet->getStyle("A".($header + 1).":A{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle("A".($header + 1).":A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A".($header + 1).":B{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $this->borders($sheet, "A{$header}:B{$last}");
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(110);
        $sheet->freezePane('A'.($header + 1));
        $this->printSetup($sheet, $header);
    }

    /** @param  Collection<int, InternshipContact>  $contacts */
    protected function summarySheet(Worksheet $sheet, Collection $contacts): void
    {
        $sheet->setTitle('Synthèse');
        $this->title($sheet, 'Synthèse', 'B', 'Au '.now()->format('d/m/Y'));

        $row = self::HEADER_ROW;
        $sheet->fromArray(['Indicateur', 'Valeur'], null, "A{$row}");
        $this->styleHeader($sheet, "A{$row}:B{$row}");
        foreach ($this->summary($contacts) as $label => $value) {
            $row++;
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValueExplicit("B{$row}", (string) $value, is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }

        $row += 2;
        $start = $row;
        $sheet->fromArray(['Résultat', 'Nombre'], null, "A{$row}");
        $this->styleHeader($sheet, "A{$row}:B{$row}");
        $counts = $contacts->countBy('status');
        foreach (InternshipContact::STATUSES as $key => $label) {
            $row++;
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $counts[$key] ?? 0);
            [, $background, $color] = InternshipContact::STATUS_STYLES[$key];
            $this->fill($sheet, "A{$row}", $background);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB($color);
        }

        $this->borders($sheet, 'A'.self::HEADER_ROW.':B'.(self::HEADER_ROW + 6));
        $this->borders($sheet, "A{$start}:B{$row}");
        $sheet->getStyle("B1:B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(14);
    }

    protected function title(Worksheet $sheet, string $title, string $lastColumn, string $subtitle): void
    {
        $sheet->setCellValue('A1', $title)->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A2', $subtitle)->mergeCells("A2:{$lastColumn}2");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::HEADER_BG);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');
        $sheet->getRowDimension(1)->setRowHeight(24);
    }

    protected function styleHeader(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $this->fill($sheet, $range, self::HEADER_BG);
        $sheet->getRowDimension((int) preg_replace('/\D/', '', explode(':', $range)[0]))->setRowHeight(30);
    }

    protected function fill(Worksheet $sheet, string $range, string $rgb): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    protected function borders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDER);
    }

    /** Impression : paysage, ajusté à la largeur, en-tête répété et pagination. */
    protected function printSetup(Worksheet $sheet, int $headerRow): void
    {
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.6);
        $sheet->getHeaderFooter()->setOddFooter('&L&8Recherche de stage — &A&R&8Page &P / &N');
    }

    protected function filename(string $extension): string
    {
        return 'recherche-stage-'.now()->format('Y-m-d').'.'.$extension;
    }
}
