<?php

namespace app\models;

use Yii;
use app\components\UtilHelper;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "contact".
 *
 * @property integer $id
 * @property integer $int_id
 * @property string $name
 * @property string $surname
 * @property string $middle_name
 * @property string $first_phone
 * @property string $second_phone
 * @property string $third_phone
 * @property string $fourth_phone
 * @property string $first_email
 * @property string $second_email
 * @property string $country
 * @property string $region
 * @property string $area
 * @property string $city
 * @property string $street
 * @property string $house
 * @property string $flat
 * @property string $status
 * @property integer $manager_id
 * @property integer $is_deleted
 */
class Contact extends \yii\db\ActiveRecord {

    public static $safe_fields = [
        'int_id',
        'name',
        'surname',
        'middle_name',
        'first_phone',
        'second_phone',
        'third_phone',
        'fourth_phone',
        'first_email',
        'second_email',
        'country',
        'region',
        'area',
        'city',
        'street',
        'house',
        'flat',
        'status',
        'manager_id',
        'is_deleted',
        'remove_tags'
    ];

    public $is_called;
    public $remove_tags;

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'contact';
    }

    public function getManager() {
        return $this->hasOne(User::className(), ['id' => 'manager_id']);
    }

    public function beforeValidate() {
        if ($this->isNewRecord) {
            $this->int_id = UtilHelper::getRandomNumbers(7);
        }
        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['int_id'], 'required'],
            [['int_id', 'manager_id'], 'integer'],
            [['first_phone','second_phone','third_phone','fourth_phone','first_email','second_email','country','region','area','city','street','house','flat','status'], 'string', 'max' => 255],
            [['name', 'surname', 'middle_name'], 'string', 'max' => 150],
            [['first_email', 'second_email'], 'string', 'max' => 255],
        ];
    }

    public static function getTableColumns() {
        return [
            1 => 'int_id',
            2 => 'surname',
            3 => 'name',
            4 => 'middle_name',
            5 => '',
            6 => 'first_phone',
            7 => 'first_email',
            8 => 't.name',
        ];
    }

    public static function getAllSafeCols() {
        return [
            'id',
            'int_id',
            'name',
            'surname',
            'middle_name',
            'first_phone',
            'second_phone',
            'third_phone',
            'fourth_phone',
            'first_email',
            'second_email',
            'country',
            'region',
            'area',
            'city',
            'street',
            'house',
            'flat'
        ];
    }

    public static function getColsForTableView() {
        $result =  [
            'id' => ['label' => 'ID', 'have_search' => false, 'orderable' => true],
            'int_id' => ['label' => '№', 'have_search' => false, 'orderable' => false],
            'surname' => ['label' => 'Фамилия', 'have_search' => true, 'orderable' => true],
            'name' => ['label' => 'Имя', 'have_search' => true, 'orderable' => true],
            'middle_name' => ['label' => 'Отчество', 'have_search' => true, 'orderable' => true],
            'link_with' => ['label' => 'Связать', 'have_search' => false, 'orderable' => false],
            'phones' => ['label' => 'Телефоны', 'have_search' => true, 'orderable' => false, 'db_cols' => ['first_phone','second_phone','third_phone','fourth_phone']],
            'emails' => ['label' => 'Email', 'have_search' => true, 'orderable' => false, 'db_cols' => ['first_email','second_email']],
            'tags' => ['label' => 'Теги', 'have_search' => true, 'orderable' => false],
            'country' => ['label' => 'Страна', 'have_search' => true, 'orderable' => true],
            'region' => ['label' => 'Регион', 'have_search' => true, 'orderable' => true],
            'area' => ['label' => 'Район', 'have_search' => true, 'orderable' => true],
            'city' => ['label' => 'Город', 'have_search' => true, 'orderable' => true],
            'street' => ['label' => 'Улица', 'have_search' => true, 'orderable' => true],
            'house' => ['label' => 'Дом', 'have_search' => true, 'orderable' => true],
            'flat' => ['label' => 'Квартира', 'have_search' => true, 'orderable' => true],
            'delete_button' => ['label' => 'Удалить', 'have_search' => false, 'orderable' => false]
        ];
        if (!Yii::$app->user->can('delete_contact')) {
            unset($result['delete_button']);
        }
        return $result;
    }



    public static function getFIOCols() {
        return [
            'surname',
            'name',
            'middle_name'
        ];
    }

    public function getFIOValues() {
        return [
            'surname' => $this->surname,
            'name' => $this->name,
            'middle_name' => $this->middle_name,
        ];
    }

    public static function getPhoneCols() {
        return [
            'first_phone',
            'second_phone',
            'third_phone',
            'fourth_phone'
        ];
    }

    public function getPhoneValues() {
        return [
            'first_phone' => $this->first_phone,
            'second_phone' => $this->second_phone,
            'third_phone' => $this->third_phone,
            'fourth_phone' => $this->fourth_phone
        ];
    }

    public static function getEmailCols() {
        return [
            'first_email',
            'second_email',
        ];
    }

    public function getEmailValues() {
        return [
            'first_email' => $this->first_email,
            'second_email' => $this->second_email
        ];
    }

    public static function getLocationCols() {
        return [
            'country',
            'region',
            'area',
            'city',
            'street',
            'house',
            'flat'
        ];
    }

    public function mergeTogether($contact)
    {
        foreach ($this->attributes as $prop_key => $prop_val) {
            if (preg_match('/surname|name|middle_name/', $prop_key)) {
                if (is_null($prop_val) || $prop_val == '') {
                    $this->$prop_key = $contact->$prop_key;
                }
            }
        }

        $phone_cols = self::getPhoneCols();
        $email_cols = self::getEmailCols();
        $location_cols = self::getLocationCols();

        $contact_phones = self::getPropValues($contact, $phone_cols);
        $contact_emails = self::getPropValues($contact, $email_cols);
        $contact_location = self::getPropValues($contact, $location_cols);

        $self_phones = self::getPropValues($this, $phone_cols);
        $self_emails = self::getPropValues($this, $email_cols);

        if (count($contact_phones['exists']) > count($self_phones['empty'])) {
            $this->addError('prop_count_miss', 'Ошибка: телефоны переполнены');
            return false;
        } elseif (count($contact_emails['exists']) > count($self_emails['empty'])) {
            $this->addError('prop_count_miss', 'Ошибка: email-ы переполнены');
            return false;
        }

        $i = 0;
        foreach ($self_phones['empty'] as $phone_key => $phone_val) {
            $this->$phone_key = isset($contact_phones['exists'][$i]) ? $contact_phones['exists'][$i] : null;
            $i++;
        }
        $i = 0;
        foreach ($self_emails['empty'] as $email_key => $email_val) {
            $this->$email_key = isset($contact_emails['exists'][$i]) ? $contact_emails['exists'][$i] : null;
            $i++;
        }

        if (count($contact_location['exists']) > 0) {
            $this->setNull($location_cols);

            foreach ($location_cols as $col) {
                $this->$col = $contact->$col;
            }
        }

        return true;
    }

    public static function getPropValues($contact, $cols)
    {
        $values = ['exists' => [], 'empty' => []];
        $cols_str = implode('|', $cols);
        $pattern = '/'.$cols_str.'/';
        foreach ($contact->attributes as $prop_key => $prop_val) {
            if (preg_match($pattern, $prop_key)) {
                if (is_null($prop_val) || $prop_val == '') {
                    $values['empty'][$prop_key] = $prop_val;
                } else {
                    $values['exists'][] = $prop_val;
                }
            }
        }
        return $values;
    }

    public function setNull($cols)
    {
        foreach ($cols as $col) {
            $this->$col = null;
        }
    }

    public static function getContactByPhone($phone) {
        return self::find()->where(['is_deleted' => '0'])
                        ->andWhere(['or', ['first_phone' => $phone], ['second_phone' => $phone], ['third_phone' => $phone], ['fourth_phone' => $phone]])
                        ->one();
    }

    public function getManagerId() {
        $manager = $this->manager;
        if ($manager && $manager->role == User::ROLE_MANAGER) {
            return $manager->id;
        }
        return null;
    }

    public static function getManagerById($id) {
        $contact = self::find()
                ->with('manager')
                ->where(['id' => $id])
                ->one();
        return $contact->manager;
    }

    public static function getById($id) {
        return self::find()->where(['id' => $id])->one();
    }

    public static function deleteById($id) {
        $contact = self::find()->where(['id' => $id])->one();
        if ($contact) {
            $contact->is_deleted = 1;
            $contact->save();
            return true;
        }
        return false;
    }

    public function edit($related) {
        $transaction = Yii::$app->db->beginTransaction();
//        $call = new Call();
        try {
            $this->save();
            if (isset($related['tags'])) {
                $this->tags = $related['tags'];
            }
            if ($this->isNewRecord) {
                $contact_history = new ContactHistory();
                $contact_history->add($this->id, 'создан контакт', 'new_contact');
                $contact_history->save();
                $contactStatusHistory = new ContactStatusHistory();
                $contactStatusHistory->add($this->id, $this->manager_id, 'lead');
                $contactStatusHistory->save();
            }
            $transaction->commit();
            return true;
        } catch (\Exception $ex) {
            $transaction->rollback();
            return false;
        }
    }

    public function getActions() {
        return $this->hasMany(Action::className(), ['contact_id' => 'id']);
    }

    public function getComments() {
        return $this->hasMany(ContactComment::className(), ['contact_id' => 'id']);
    }

    public function getPhones() {
        return $this->hasMany(Call::className(), ['contact_id' => 'id']);
    }

    public function getTags() {
        return $this->hasMany(Tag::className(), ['id' => 'tag_id'])->viaTable('contact_tag', ['contact_id' => 'id']);
    }

    public function setTags($new_tags) {
        if ($this->remove_tags == true) {
            $this->unlinkAll('tags');
        }
        foreach ($new_tags as $new_tag) {
            $new_tag->save();
            if (!ContactTag::find()->where(['contact_id' => $this->id, 'tag_id' => $new_tag->id])->exists()) {
                $this->link('tags', $new_tag);
            }
        }
    }

    public static function getTagContacts($filters, $user_role, $user_id = null)
    {
        if ($user_role == 'operator') {
            $filters['extra']['operator_id'] = $user_id;
            $queue_ids = Contact::getContactsInPool(['contact_id'], $user_id, true, 'contact_id');
            $filters['extra']['queue_ids'] = $queue_ids;
        }

        $res_data = [
            'called_contacts' => [],
            'contacts' => [],
            'total_count' => 0,
            'total_filtering_count' => 0,
            "count_all" => 0,
            "count_called" => 0
        ];

        $query = Contact::find()->with([
            'phones' => function($query) use ($filters) {
                $query->where(['=', Call::tableName().'.tag_id', $filters['extra']['tag_id']]);
            },
        ])->joinWith(['phones', 'phones.callManagers', 'actions.comment'])->orderBy(Contact::tableName().'.id')->distinct();

        $main_where = [];
        foreach ($filters['main'] as $main_key => $main_val) {
            $main_where[Contact::tableName().'.'.$main_key] = $main_val;
        }
        $query->where($main_where);

        $extra_where = [];
        foreach ($filters['extra'] as $extra_name => $extra_val) {
            switch ($extra_name) {
                case 'manager_id':
                    $extra_where[CallManager::tableName().'.'.$extra_name] = $extra_val;
                    break;
                case 'status':
                    $extra_val = explode('|', $extra_val);
                    $statuses = explode('_', $extra_val[0]);
                    $types = explode('_', $extra_val[1]);
                    if (count($statuses) > 0) {
                        $extra_where[Call::tableName().'.'.$extra_name] = $statuses;
                    }
                    if (count($types) > 0) {
                        $extra_where[Call::tableName().'.type'] = $types;
                    }
                    break;
                case 'attitude_level':
                    $extra_where[Call::tableName().'.'.$extra_name] = $extra_val;
                    break;
            }
        }

        if (!empty($filters['extra']['queue_ids']) && count($filters['extra']['queue_ids']) > 0) {
            $query->andWhere(['not in', Contact::tableName().'.id', $filters['extra']['queue_ids']]);
        }

        $query_called = clone $query;


        $query_called->andWhere(['=', Call::tableName().'.tag_id', $filters['extra']['tag_id']]);

        $res_data['count_called'] = $query_called->count();
        $res_data['count_all'] = $res_data['count_called'];

        $query_called_ids = clone $query_called;
        $called_contacts_ids = $query_called_ids->all();
        $called_ids = [];
        foreach ($called_contacts_ids as &$called) {
            $called_ids[] = $called->id;
        }
        $query->andWhere(['not in', Contact::tableName().'.id', $called_ids]);

        if (!empty($filters['extra']['operator_id'])) {
            $query_called->andWhere([CallManager::tableName().'.manager_id' => $filters['extra']['operator_id']]);
        }

        $res_data['total_count'] = $query_called->count();

        if (!empty($filters['extra']['comment'])) {
            $query_called->andWhere(['like', ActionComment::tableName().'.comment', $filters['extra']['comment']]);
        }

        $query_called->andWhere($extra_where);

        $res_data['total_filtering_count'] = $query_called->count();

        $called_contacts = $query_called->all();
        $contacts = $query->all();

        array_walk($called_contacts, function(&$contact) {
            $contact->is_called = true;
        });

        $res_data['count_all'] += count($contacts);

        if ($user_role == 'operator') {
            $contacts = array_slice($contacts, 0, 1);
            Contact::addContInPool($contacts[0]['id'], $user_id, $filters['extra']['tag_id']);
        }

        $res_data['called_contacts'] = $called_contacts;

        if (!$filters['filtering']) {
            $res_data['contacts'] = $contacts;
            $res_data['total_filtering_count'] += count($contacts);
        } else {
            $res_data['contacts'] = [];
        }

        $res_data['total_count'] += count($contacts);

        return $res_data;
    }

    public static function getContactsInPool($select = [], $manager_id = null, $as_array = false, $array_val = '', $rawSql = false)
    {
        $query = TempContactsPool::find()->select($select)->joinWith('contact');
        if ($manager_id) {
            $query->andWhere(['!=', TempContactsPool::tableName().'.manager_id', $manager_id]);
        }
        if ($rawSql) {
            return $query->createCommand()->rawSql;
        }
        if ($as_array) {
            $query->asArray();
            if ($array_val != '') {
                $contacts = $query->all();
                $res = [];
                foreach ($contacts as $contact) {
                    $res[] = $contact[$array_val];
                }
                return $res;
            }
        }
        $dump = $query->createCommand()->rawSql;
        return $query->all();
    }

    public static function addContInPool($contact_id, $manager_id, $tag_id)
    {
        if (!TempContactsPool::find()->where(['contact_id' => $contact_id])->exists()) {
            $cont_pool = new TempContactsPool(['contact_id' => $contact_id, 'manager_id' => $manager_id, 'tag_id' => $tag_id]);
            return $cont_pool->save();
        }
        return false;
    }

    public static function removeContInPool($contact_id)
    {
        $cont_pool = TempContactsPool::findOne(['contact_id' => $contact_id]);
        if ($cont_pool) {
            return $cont_pool->delete();
        }
        return false;
    }

}
