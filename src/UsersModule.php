<?php

namespace DevGroup\Users;

use DevGroup\TagDependencyHelper\LazyCache;
use DevGroup\Users\helpers\ModelMapHelper;
use DevGroup\Users\models\SocialService;
use DevGroup\Users\models\User;
use DevGroup\Users\scenarios\BaseAuthorizationPair;
use Yii;
use yii\authclient\Collection;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\Event;
use yii\base\Module;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;

class UsersModule extends Module implements BootstrapInterface
{
    protected $defaultModelMap = [
        'User' => [
            'class' => 'DevGroup\Users\models\User',
        ],
        'RegistrationForm' => [
            'class' => 'DevGroup\Users\models\RegistrationForm',
        ],
        'LoginForm' => [
            'class' => 'DevGroup\Users\models\LoginForm',
        ],
    ];
    public $modelMap = [];

    public $authorizationScenario = [
        'class' => 'DevGroup\Users\scenarios\UsernamePassword',
    ];

    /** @var BaseAuthorizationPair Authorization scenario class instance */
    private $authorizationScenarioInstance = null;

    /** @var bool If E-Mail confirmation needed */
    public $emailConfirmationNeeded = true;

    /** @var bool Allow inactive accounts to login */
    public $allowLoginInactiveAccounts = false;

    /** @var int Length of automatically generated password */
    public $generatedPasswordLength = 8;

    /** @var int Login duration in seconds, default to 30 days, applies only if remember me is checked */
    public $loginDuration = 2592000;

    /** @var bool Whether to log last login time */
    public $logLastLoginTime = false;

    /** @var bool Enable login and registration through social networks */
    public $enableSocialNetworks = true;

    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        $this->buildModelMap();

        $app->i18n->translations['users'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR . 'messages',
        ];


        $app->on(Application::EVENT_BEFORE_REQUEST, function () {
            if ($this->logLastLoginTime === true) {
                Event::on(ModelMapHelper::User(), User::EVENT_LOGIN, function ( Event $event ) {
                    /** @var User $user */
                    $user = $event->sender;
                    $user->last_login_at = time();
                    $user->save(true, ['last_login_at']);
                });
            }
        });

    }

    /**
     * Builds model map, setups di container
     */
    private function buildModelMap()
    {
        $this->modelMap = ArrayHelper::merge($this->defaultModelMap, $this->modelMap);
        foreach ($this->modelMap as $modelName => $configuration) {
            Yii::$container->set($configuration['class'], $configuration);
        }
    }

    /**
     * @return UsersModule Module instance in application
     */
    public static function module()
    {
        return Yii::$app->getModule('users');
    }

    /**
     * @return \DevGroup\Users\scenarios\BaseAuthorizationPair
     * @throws \yii\base\InvalidConfigException
     */
    public function authorizationScenario()
    {
        if ($this->authorizationScenarioInstance === null) {
            $this->authorizationScenarioInstance = Yii::createObject($this->authorizationScenario);
        }
        return $this->authorizationScenarioInstance;
    }
}