<?php

namespace DevGroup\Users\models;

use yii\base\DynamicModel;
use Yii;
use yii\web\NotFoundHttpException;

class RequestResetPasswordForm extends DynamicModel
{

    const EVENT_BEFORE_RESET_PASSWORD = 'event-before-request-reset-password';
    const EVENT_AFTER_RESET_PASSWORD = 'event-after-request-reset-password';

    public $email;
    protected $user;

    public function rules()
    {
        return [
            [['email'], 'required'],
            [['email'], 'email'],
            [['email'], 'exist', 'targetClass' => User::className(), 'targetAttribute' => 'email']
        ];
    }

    public function attributeLabels()
    {
        return [
            'email' => Yii::t('users', 'E-Mail')
        ];
    }


    public function getUser()
    {
        return $this->user;
    }

    public function resetPassword()
    {
        /** @var User $user */
        $this->user = User::findOne(['email' => $this->email]);
        if ($this->user === null) {
            throw new NotFoundHttpException(Yii::t('users', 'No user identity found'));
        }

        $this->trigger(self::EVENT_BEFORE_RESET_PASSWORD);
        $this->user->generatePasswordResetToken();
        if ($this->user->save()) {
            $this->trigger(self::EVENT_AFTER_RESET_PASSWORD);
            return true;
        }

        return false;
    }
}
