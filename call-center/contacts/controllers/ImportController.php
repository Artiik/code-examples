<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use app\components\CSVReader;
use app\models\Contact;
use app\models\forms\ImportContactForm;

class ImportController extends BaseController
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['index', 'csv', 'get-status'],
                        'allow' => true,
                        'roles' => ['admin'],
                    ]
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionCsv()
    {
        $csv_reader = new CSVReader([
            'filename' => $_FILES['csv_file']['tmp_name'],
            'fgetcsvOptions' => [
                'delimiter' => ';'
            ]
        ]);
        $new_contacts = $csv_reader->readFile();
        if (count($new_contacts[0]) !== 13) {
            return $this->json(null, 415, [
                'Не корректное кол-во колонок'
            ]);
        }
        //getting CSV headers for report file from imported CSV
        $report_headers = $new_contacts[0];
        array_unshift($report_headers,"Error type");

        unset($new_contacts[0]);
        $error = false;
        $imported = 0;

        $report_file_name = time() . '.csv';
        //adding first line to report CSV file
        $report_file = fopen(Yii::getAlias('@web_folder') . '/reports/' . $report_file_name, "a+");
        fwrite($report_file, iconv("UTF-8", "windows-1251//TRANSLIT", implode($report_headers, ';')) . "\r\n");
        fclose($report_file);

        $updated = 0;
        $contact_ids = [];
        for ($i = 1; $i <= count($new_contacts); $i++) {
            $contact_data = $new_contacts[$i];
            $import_contact_form_cols = ImportContactForm::getAllCols();
            $attributes = [];
            $col_cnt = 0;
            foreach ($import_contact_form_cols as $col) {
                $attributes[$col] = iconv(mb_detect_encoding($contact_data[$col_cnt], mb_detect_order(), true), "UTF-8", $contact_data[$col_cnt]);
                $col_cnt++;
            }
            $import_contact_form = new ImportContactForm();
            $import_contact_form->attributes = $attributes;
            if ($import_contact_form->validate()) {
                try {
                    $contact = new Contact();
                    $contact->manager_id = Yii::$app->user->identity->id;
                    $contact->attributes = $import_contact_form->attributes;

                    if ($contact->edit(['tags' => $import_contact_form->tags])) {
                        $imported++;
                        $contact_ids[] = $contact->id;
                    } else {
                        $this->writeReport($report_file_name, $attributes, $contact);
                        $error = true;
                    }
                } catch (\Exception $ex) {
                    $this->json(false, 500);
                }
            } else {
                $this->writeReport($report_file_name, $attributes, $import_contact_form);

                if ($import_contact_form->conflict_id !== null) {
                    $cur_contact = Contact::findOne($import_contact_form->conflict_id);
                    if ($cur_contact !== null) {
                        $contact_ids[] = $cur_contact->id;
                        $exists_tags = $cur_contact->tags;
                        if (count($exists_tags) < count($import_contact_form->tags)) {
                            $updated++;
                        }
                        $cur_contact->edit(['tags' => $import_contact_form->tags]);
                    }
                }

                $error = true;
            }
        }
        // Получить список контактов (для вставки в тег)

        if ($error) {
            $this->json([
                'report_file' => Yii::getAlias('@web') . '/reports/' . $report_file_name,
                'imported' => $imported,
                'count' => count($new_contacts),
                'updated' => $updated,
                'contact_ids' => $contact_ids
            ], 415);
        } else {
            $this->json([
                'imported' => $imported,
                'count' => count($new_contacts),
                'contact_ids' => $contact_ids
            ], 200);
        }

    }

    private function writeReport($file_name, $attributes, $instance)
    {
        //getting errors depending on $intance object
        $errors = $instance->getErrors();
        $report_file = fopen(Yii::getAlias('@web_folder') . '/reports/' . $file_name, "a+");
        //preparing attributes line which called an import error
        //disabling encoding to Win-1251
        //$error_line = iconv("UTF-8", "windows-1251//TRANSLIT", implode($attributes, ';'));
        $error_line = implode($attributes, ';');
        //combining error in one object if it's related on same string
        $combined_error = [];
        foreach ($errors as $error) {
            array_push($combined_error,$error[0]);
        }
        //disabling encoding to Win-1251
        //fwrite($report_file, iconv("UTF-8", "windows-1251//TRANSLIT", implode($combined_error, ', ')) . ";" . $error_line . "\n");
        fwrite($report_file, implode($combined_error, ', ') . ";" . $error_line . "\r\n");
        fclose($report_file);
    }
}