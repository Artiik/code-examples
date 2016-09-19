<?php

namespace app\controllers;

use app\components\CSVExport;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use app\models\Tag;
use app\models\User;
use app\models\Contact;
use app\models\Call;
//use app\models\ContactCalled;
//use app\models\TempContactsPool;
use app\components\widgets\TagContactsTableWidget;
use app\components\widgets\TagsSelectWidget;
use app\components\widgets\ContactTagTableWidget;

class TagsController extends BaseController {
    public function behaviors() {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => [
                            'index',
                            'gettags',
                            'getdata',
                            'getusers',
                            'getcontacts',
                            'edit',
                            'delete',
                            'export-csv'
                        ],
                        'allow' => true,
                        'roles' => ['admin', 'manager'],
                    ],
                    [
                        'actions' => [
                            'index',
                            'gettags',
                            'getdata',
                            'getusers',
                        ],
                        'allow' => true,
                        'roles' => ['operator'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                    'edit' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $request_data = Yii::$app->request->get();
        $session = Yii::$app->session;
        $hide_contact_columns = $session->get('contact_hide_columns');
        if (!$hide_contact_columns) {
            $hide_contact_columns = [];
        }

        $contact_cols = Tag::getContactsForTagTableView();

        $table_contact_cols =  $contact_cols;
        $filter_contact_cols =  $contact_cols;
        unset($filter_contact_cols['id']);

        $data = [
            'hide_contact_columns' => $hide_contact_columns,
            'table_contact_cols' => $table_contact_cols,
            'filter_contact_cols' => $filter_contact_cols,
        ];

        $call_statuses = Call::getCallStatuses();
        $call_statuses[0]['name'] = Call::CALL_STATUS_ANSWERED.'|'.Call::CALL_INCOMING;
        $call_statuses[0]['label'] = 'Успешный';
        $call_statuses[2]['name'] = Call::CALL_STATUS_MISSED.'|'.Call::CALL_INCOMING;
        $call_statuses[3]['name'] = Call::CALL_STATUS_FAILURE.'|'.Call::CALL_INCOMING;
        unset($call_statuses[1]);

        $attitude_levels = Call::getAttitudeLevels();

        if (Yii::$app->user->identity->role == User::ROLE_ADMIN) {
            $managers = User::find()->all();
        } else if (Yii::$app->user->identity->role == User::ROLE_MANAGER) {
            $managers = User::find()->where(['role' => [User::ROLE_MANAGER, User::ROLE_OPERATOR]])->all();
        } else {
            $managers = null;
        }
        $data['managers'] = $managers;
        $data['call_statuses'] = $call_statuses;
        $data['attitude_levels'] = $attitude_levels;

        return $this->render('index', $data);
    }

    public function actionGettags() {
        $request_data = Yii::$app->request->get();
        $term = $request_data['term'];
        $query = Tag::find();
        $user_id = Yii::$app->user->identity->getId();
//        $user_oper = Yii::$app->user->can('operator');
        $user_role = Yii::$app->user->identity->getUserRole();
        if ($user_role == 'manager' || $user_role == 'operator') {
            $query->joinWith('users')->andWhere(['=', 'user.id', $user_id]);
            if ($user_role == 'operator') {
                $query->andWhere(['=', 'tag.as_task', 1]);
            }
        }
        $query->andWhere(['like', 'tag.name', $term]);

        $dump = $query->createCommand()->rawSql;
        $tags = $query->all();
        $tags_widget = new TagsSelectWidget();
        $tags_widget->tags = $tags;
        $data = $tags_widget->run();

        if (count($tags) > 0) {
            $this->json(['items' => $data], 200);
        } else {
            $this->json(['items' => []], 404);
        }
    }

    // Получение списка контактов для вывода в модальном окне.
    public function actionGetcontacts()
    {
        $request_data = Yii::$app->request->get();
        $query = Contact::find()->with('manager', 'tags')->distinct('contact.id');
        $query->andWhere(['contact.is_deleted' => '0']);
        $columns = Tag::getContactsForTagTableView();
        $user_id = Yii::$app->user->identity->getId();
        $user_role = Yii::$app->user->identity->getUserRole();

        //Sorting
        $sorting = [];
        if (isset($request_data['order'])) {
            $order_by_sort = $request_data['order'][0]['dir'] == 'asc' ? SORT_ASC : SORT_DESC;
            $sort_column = array_keys($columns)[$request_data['order'][0]['column']];

            $sorting = [
                $sort_column => $order_by_sort
            ];
        } else {
            $sorting = [
                Contact::tableName().'.id' => SORT_DESC
            ];
        }

        if ($user_role == 'manager') {
            $query->joinWith('tags.users')->andWhere(['user.id' => $user_id]);
        }
        $query_total = clone $query;
        $total_count = $query_total->count();

        //Filtering
        foreach ($request_data['columns'] as $column) {
            if (!empty($column['search']['value'])) {
                if (isset($columns[$column['name']]['db_cols'])) {
                    $db_cols_where = ['or'];
                    foreach ($columns[$column['name']]['db_cols'] as $db_col_i => $db_col_v) {
                        $db_cols_where[] = ['like', 'contact.'.$db_col_v, $column['search']['value']];
                    }
                    $query->andWhere($db_cols_where);
                } elseif ($column['name'] == 'tags') {
                    $query->joinWith('tags')->andWhere(['like', 'tag.name', $column['search']['value']]);
                } else {
                    $query->andWhere(['like', 'contact.'.$column['name'], $column['search']['value']]);
                }
            }
        }

        $dump = $query->createCommand()->rawSql;
        $total_filtering_count = $query->count();
        $query
            ->orderBy($sorting)
            ->limit($request_data['length'])
            ->offset($request_data['start']);

        $contacts = $query->all();
        $contact_widget = new ContactTagTableWidget();
        $contact_widget->contacts = $contacts;
        $data = $contact_widget->run();

        $query_all = clone $query;
        $query_all->limit(null)->offset(null);
        $contacts_all = $query_all->all();
        $contacts_str = '';
        foreach ($contacts_all as $contact) {
            $contacts_str .= $contact['id'] . ',';
        }
        $contacts_str = rtrim($contacts_str, ",");

        $json_data = array(
            "contacts" => $contacts_str,
            "draw" => intval($request_data['draw']),
            "recordsTotal" => intval($total_count),
            "recordsFiltered" => intval($total_filtering_count),
            "data" => $data   // total data array
        );
        echo json_encode($json_data);
        die;
    }

    public function actionEdit()
    {
        $post = Yii::$app->request->post();

        try {
            $tag = null;
            if (isset($post['id']) && !empty($post['id'])) {
                $tag = Tag::getById($post['id']);
            } else {
                $tag = new Tag();
            }
            unset($post['_csrf']);
            unset($post['id']);
            $tag->attributes = $post;
            if ($tag->edit(['user_ids' => $post['tag_users'], 'contact_ids' => $post['tag_contacts']])) {
                $this->json(['id' => $tag->id], 200);
            } else {
                $this->json(false, 415, $tag->getErrors());
            }
        } catch (\Exception $ex) {
            $this->json(false, 500);
        }
    }

    public function actionGetusers()
    {
//        $request_data = Yii::$app->request->get();
        $query = User::find();
        $user_role = Yii::$app->user->identity->getUserRole();
        switch ($user_role) {
            case 'admin':
                $query->andWhere(['role' => [User::ROLE_OPERATOR, User::ROLE_MANAGER]]);
                break;
            case 'manager':
                $query->andWhere(['role' => User::ROLE_OPERATOR]);
                break;
            case 'operator':
                $query->andWhere(['role' => User::ROLE_OPERATOR]);
                break;
        }

        $dump = $query->createCommand()->rawSql;
        $users = $query->asArray()->all();

        if (count($users) > 0) {
            $this->json(['items' => $users], 200);
        } else {
            $this->json(['items' => []], 404);
        }
    }

    public function actionExportCsv()
    {
        $request_data = Yii::$app->request->get();

        if (!empty($request_data['contact_ids'])) {
            $contact_ids = explode(',', $request_data['contact_ids']);
            $tag_id = $request_data['tag_id'];
            $user_role = Yii::$app->user->identity->getUserRole();

            $filters = ['filtering' => false, 'main' => [], 'extra' => []];
            $filters['main']['id'] = $contact_ids;
            $filters['extra']['tag_id'] = $tag_id;
            if (!empty($request_data['manager_id'])) {
                $filters['extra']['manager_id'] = $request_data['manager_id'];
                $filters['filtering'] = true;
            }
            if (!empty($request_data['status'])) {
                $filters['extra']['status'] = $request_data['status'];
                $filters['filtering'] = true;
            }
            if (!empty($request_data['comment'])) {
                $filters['extra']['comment'] = $request_data['comment'];
                $filters['filtering'] = true;
            }
            if (!empty($request_data['attitude_level'])) {
                $filters['extra']['attitude_level'] = $request_data['attitude_level'];
                $filters['filtering'] = true;
            }

            $tag_contacts = Contact::getTagContacts($filters, $user_role);

            $contacts = array_merge($tag_contacts['called_contacts'], $tag_contacts['contacts']);

            $tag_contacts_widget = new TagContactsTableWidget();
            $tag_contacts_widget->tag_contacts = $contacts;
            $tag_contacts_widget->user_role = $user_role;
            $tag_contacts_widget->export = true;
            $data = $tag_contacts_widget->run();

            $csv_export = new CSVExport([
                'filename' => 'called_contacts',
                'csvOptions' => [
                    'delimiter' => ';'
                ]
            ]);
            $columns = ['int_id', 'surname', 'Phones', 'operator', 'status', 'comment', 'attitude'];
            $csv_export->addRow($columns);
            foreach ($data as $item) {
                $csv_export->addRow($item);
            }
            $csv_export->export();
        }
        $this->redirect('/tags');
    }

    public function actionGetdata()
    {
        $request_data = Yii::$app->request->get();
        $tag_id = $request_data['columns'][1]['search']['value'];
        if (!empty($request_data['columns'][0]['search']['value'])) {
            $filter_ids = explode(',', $request_data['columns'][0]['search']['value']);
        }

        if (isset($filter_ids) && count($filter_ids) > 0) {
            $user_id = Yii::$app->user->identity->getId();
            $user_role = Yii::$app->user->identity->getUserRole();

            //Filtering
            $filters = ['filtering' => false, 'main' => [], 'extra' => []];
            $filters['main']['id'] = $filter_ids;
            $filters['extra']['tag_id'] = $tag_id;
            if (!empty($request_data['columns'][4]['search']['value'])) {
                $filters['extra']['manager_id'] = $request_data['columns'][4]['search']['value'];
                $filters['filtering'] = true;
            }
            if (!empty($request_data['columns'][5]['search']['value'])) {
                $filters['extra']['status'] = $request_data['columns'][5]['search']['value'];
                $filters['filtering'] = true;
            }
            if (!empty($request_data['columns'][6]['search']['value'])) {
                $filters['extra']['comment'] = $request_data['columns'][6]['search']['value'];
                $filters['filtering'] = true;
            }
            if (!empty($request_data['columns'][7]['search']['value'])) {
                $filters['extra']['attitude_level'] = $request_data['columns'][7]['search']['value'];
                $filters['filtering'] = true;
            }

            if ($user_role == 'operator') {
                $tag_contacts = Contact::getTagContacts($filters, $user_role, $user_id);
//                $dump = $query->createCommand()->rawSql;
            } else {
                $tag_contacts = Contact::getTagContacts($filters, $user_role);
            }

            $contacts = array_merge($tag_contacts['called_contacts'], $tag_contacts['contacts']);

            $tag_contacts_widget = new TagContactsTableWidget();
            $tag_contacts_widget->tag_contacts = $contacts;
            $tag_contacts_widget->user_role = $user_role;
            $data = $tag_contacts_widget->run();

            $json_data = array(
                "draw" => intval($request_data['draw']),
                "recordsTotal" => $tag_contacts['total_count'],
                "recordsFiltered" => $tag_contacts['total_filtering_count'],
                "data" => $data,   // total data array
                "contact_count" => [
                    "count_all" => $tag_contacts['count_all'],
                    "count_called" => $tag_contacts['count_called']
                ]
            );
            echo json_encode($json_data);
            die;
        } else {
            $json_data = array(
                "draw" => intval($request_data['draw']),
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],   // total data array
                "contact_count" => [
                    "count_all" => 0,
                    "count_called" => 0
                ]
            );
            echo json_encode($json_data);
            die;
        }
    }

}