<?php

namespace DevGroup\Users;

use DevGroup\TagDependencyHelper\LazyCache;
use DevGroup\Users\actions\ResetPassword;
use DevGroup\Users\handlers\EmailHandler;
use DevGroup\Users\helpers\ModelMapHelper;
use DevGroup\Users\models\ResetPasswordForm;
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
use yii\widgets\ActiveField;

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

    public $requiredUserAttributes = [
        'username',
        'email',
    ];

    public $recommendedUserAttributes = [
        'phone',
    ];

    public $disabledUserAttributes = [];

    public $recommendedFieldsMaxPrompts = 1;

    protected $defaultRoutes = [
        '@profile-update' => '/users/profile/update',
        '@change-password' => '/users/profile/change-password',
        '@login' => '/users/auth/login',
        '@social' => '/users/auth/social',
        '@logout' => '/users/auth/logout',
        '@registration' => '/users/auth/registration',
        '@reset-password' => '/users/auth/reset-password',
    ];

    public $routes = [];

    protected $defaultHandlers = [
       'sendMailAfterResetPassword' => [
            'class' => ResetPasswordForm::class,
            'event_name' => ResetPasswordForm::EVENT_AFTER_RESET_PASSWORD,
            'event_handler' => [
                EmailHandler::class,
                'sendMailAfterResetPassword'
            ]
        ]
    ];

    public $handlers = [];


    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        $this->buildModelMap();
        $this->buildAliases();

        $app->i18n->translations['users'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR . 'messages',
        ];

        foreach(ArrayHelper::merge($this->defaultHandlers, $this->handlers) as $eventData){
            Event::on($eventData['class'], $eventData['event_name'], $eventData['event_handler']);
        }

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

        $this->frontendMonsterPatch();

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
     * Sets needed routes aliases
     */
    protected function buildAliases()
    {
        $this->routes = ArrayHelper::merge($this->defaultRoutes, $this->routes);
        foreach ($this->routes as $alias => $route) {
            Yii::setAlias($alias, $route);
        }
    }

    protected function frontendMonsterPatch()
    {
        Yii::$container->set(ActiveField::className(), [
            'options' => [
                'class' => 'm-form__col',
            ],
        ]);
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
