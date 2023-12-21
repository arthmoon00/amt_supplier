<?php

namespace common\models;

use App\CatalogueItemInterface;
use yii\db\ActiveRecord;

/**
 * User model
 *
 * @property integer $id
 * @property string $author
 *
 * @property string $name
 */
class Book extends ActiveRecord implements CatalogueItemInterface
{
    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    public static function tableName(): string
    {
        return '{{%book}}';
    }
    public function getSummary(): string
    {

    }

    public function rules()
    {
        return [
            [['name', 'author'], 'safe'],
        ];
    }
}