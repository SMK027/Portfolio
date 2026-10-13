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
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
            'headings' => self::HEADINGS,
        ])->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

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
        $spreadsheet->getProperties()->setTitle('Recherche de stage');

        // Feuille 1 : contacts
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Contacts');
        $sheet->fromArray(self::HEADINGS, null, 'A1');

        foreach ($contacts->values() as $i => $contact) {
            $row = $i + 2;
            $values = [
                $contact->company, $contact->address, $contact->contact_name,
                ExcelDate::PHPToExcel($contact->contacted_on), $contact->methodLabel(), $contact->statusLabel(),
                $contact->follow_up_on ? ExcelDate::PHPToExcel($contact->follow_up_on) : null,
                $contact->followUpMethodLabel(), $contact->notes,
            ];
            foreach ($values as $col => $value) {
                $cell = $sheet->getCell([$col + 1, $row]);
                in_array($col, [3, 6], true) || $value === null
                    ? $cell->setValue($value)
                    : $cell->setValueExplicit($value, DataType::TYPE_STRING); // pas de formule injectée
            }
        }

        $last = max(2, $contacts->count() + 1);
        $sheet->getStyle("D2:D{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle("G2:G{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle("A2:I{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $this->styleHeader($sheet, 'A1:I1');
        foreach (['A' => 28, 'B' => 32, 'C' => 20, 'D' => 14, 'E' => 14, 'F' => 22, 'G' => 14, 'H' => 14, 'I' => 50] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:I{$last}");

        // Feuille 2 : remarques
        $notes = $spreadsheet->createSheet()->setTitle('Remarques');
        $notes->fromArray(['Date', 'Remarque'], null, 'A1');
        foreach ($remarks->values() as $i => $remark) {
            $notes->setCellValue([1, $i + 2], ExcelDate::PHPToExcel($remark->noted_on));
            $notes->setCellValueExplicit([2, $i + 2], $remark->body, DataType::TYPE_STRING);
        }
        $lastRemark = max(2, $remarks->count() + 1);
        $notes->getStyle("A2:A{$lastRemark}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $notes->getStyle("A2:B{$lastRemark}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $this->styleHeader($notes, 'A1:B1');
        $notes->getColumnDimension('A')->setWidth(14);
        $notes->getColumnDimension('B')->setWidth(100);

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->filename('xlsx'), [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    protected function styleHeader($sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
    }

    protected function filename(string $extension): string
    {
        return 'recherche-stage-'.now()->format('Y-m-d').'.'.$extension;
    }
}
