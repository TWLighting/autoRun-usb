<?php

namespace App\Http\Controllers\Admin;

use App\Libraries\Functions;
use Illuminate\Http\Request;
use DB;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class TransactionController extends AdminController
{
    public function getList(Request $request)
    {
        $data = $this->getTransactionData($request)
            ->paginate($request->input('perpage', 20));

        $data->getCollection()->transform(function ($value) {
            $value->amt = round($value->amt, 2);
            $value->balance = round($value->balance, 2);
            return $value;
        });
        return $this->presenter->json($data, "成功", 1);
    }

    public function exportData(Request $request)
    {
        set_time_limit(600);
        ini_set('memory_limit', '1024M');

        $resultFile = tempnam(sys_get_temp_dir(), 'TransactionExportData');
        $resultExcel = new Spreadsheet();
        $sheet = $resultExcel->getActiveSheet();
        $title = [
            '商戶号', '银行名称', '卡号',  '姓名',
            '交易时间', '金额', '餘額',  '對方信息',
            '交易類型', '備註',  '记录回传时间',
        ];

        $sheet->fromArray($title, NULL, 'A1');
        $i = 2;
        $data = $this->getTransactionData($request);
        $count = $data->count();
        if ($count > 500000) {
            return view('jsalert', ['msg' => '资料笔数超过五十万笔，请缩小搜寻范围再重试。']);
        } elseif ($count == 0) {
            return view('jsalert', ['msg' => '无资料。']);
        }

        $data = $data->get();

        foreach ($data as $row) {
            $rowData = [
                $row->account,
                $row->bank_name,
                $row->card_no,
                $row->acc_name,
                $row->trans_time,
                round($row->amt, 2),
                round($row->balance, 2),
                $row->tran_info,
                ($row->amt >= 0) ? '转入' : '转出',
                $row->note,
                $row->bankapi_time,
            ];
            $sheet->fromArray($rowData, NULL, 'A'.$i);
            // 因遗漏字元，卡号必须以TYPE_STRING方式存入
            $sheet->setCellValueExplicit('C'.$i, $rowData[2], DataType::TYPE_STRING);
            $i++;
        }

        // width
        foreach(range('B', 'K') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        // format
        foreach(['C', 'H', 'I', 'K'] as $columnID) {
            $range = sprintf('%s2:%s%s', $columnID, $columnID, $i);
            $sheet->getStyle($range)
                ->getNumberFormat()
                ->setFormatCode('#');
        }

        $writer = new Xlsx($resultExcel);
        $writer->save($resultFile);

        return response()->download($resultFile, date("YmdHis").'网银记录.xlsx');
    }

    public function getSumAmount(Request $request)
    {
        $data = $this->getTransactionData($request, true)
            ->first();
        $data->payTotal = round($data->payTotal, 2);
        $data->getTotal = round($data->getTotal, 2);
        return $this->presenter->json($data, "成功", 1);
    }

    private function getTransactionData($request, $selectSum = false)
    {
        $data = DB::table('transaction_info AS ti')
            ->join('bank_card_info AS bci', 'ti.card_no', '=', 'bci.card_no')
            ->join('account AS a', 'bci.account_id', '=', 'a.id')
            ->orderBy('ti.bankapi_time', 'desc')
            ->orderBy('ti.id', 'desc');

        if ($selectSum) {
            $data->selectRaw('SUM(CASE WHEN amt < 0 THEN amt ELSE 0 END) as payTotal')
                ->selectRaw('SUM(CASE WHEN amt >= 0 THEN amt ELSE 0 END) as getTotal');
        } else {
            $data->select([
                'a.account', 'bci.bank_name', 'bci.card_no', 'bci.acc_name', 'ti.*'
            ]);
        }

        if (!Functions::isAdmin($request)) {
            $data->where('bci.account_id', $request->session()->get('topAccountId'));
        } elseif ($request->filled('account')) {
            $data->where('a.account', $request->input('account'));
        }

        if ($request->filled('bankcard')) {
            $data->where('bci.card_no', $request->input('bankcard'));
        }

        if ($request->filled('bankname')) {
            $data->where('bci.bank_name', $request->input('bankname'));
        }

        if ($request->filled('acc_name')) {
            $data->where('bci.acc_name', 'like', '%' . $request->input('acc_name') . '%');
        }

        if ($request->filled('tran_type')) {
            if ($request->input('tran_type') == 1) {
                $data->where('ti.amt', '>', 0);
            } else {
                $data->where('ti.amt', '<', 0);
            }
        }

        if($request->filled('startDate')){
            $data->where('ti.trans_time', '>=', $request->input('startDate'));
        }
        if($request->filled('endDate')){
            $data->where('ti.trans_time', '<=', $request->input('endDate'));
        }

        return $data;
    }
}
