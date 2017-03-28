<?php

namespace PG\I18N;

/**
 * I18N provides features related with internationalization (I18N) and localization (L10N).
 *
 * @property MessageFormatter $messageFormatter The message formatter to be used to format message via ICU
 * message format. Note that the type of this property differs in getter and setter. See
 * [[getMessageFormatter()]] and [[setMessageFormatter()]] for details.
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
class I18N
{
    /**
     * @var null
     */
    public static $i18n = null;

    /**
     * @var array list of [[MessageSource]] configurations or objects. The array keys are message
     * category patterns, and the array values are the corresponding [[MessageSource]] objects or the configurations
     * for creating the [[MessageSource]] objects.
     *
     * The message category patterns can contain the wildcard '*' at the end to match multiple categories with the same prefix.
     * For example, 'app/*' matches both 'app/cat1' and 'app/cat2'.
     *
     * The '*' category pattern will match all categories that do not match any other category patterns.
     *
     * This property may be modified on the fly by extensions who want to have their own message sources
     * registered under their own namespaces.
     *
     * The category "yii" and "app" are always defined. The former refers to the messages used in the Yii core
     * framework code, while the latter refers to the default message category for custom application code.
     * By default, both of these categories use [[PhpMessageSource]] and the corresponding message files are
     * stored under "@app/messages", respectively.
     *
     * You may override the configuration of both categories.
     */
    public $translations;

    /**
     * 实例化
     * @param array $config 配置，如：
     * [
     *     'class' => 'PhpMessageSource',
     *     'sourceLanguage' => 'en_us',
     *     'basePath' => '<DIR>/messages', // 翻译配置文件路径
     * ]
     */
    public static function getInstance(array $config)
    {
        if (self::$i18n === null) {
            self::$i18n = new self($config);
        }

        return self::$i18n;
    }

    /**
     * Translates a message to the specified language.
     *
     * This is a shortcut method of [[\PG\I18N::translate()]].
     *
     * The translation will be conducted according to the message category and the target language will be used.
     *
     * You can add parameters to a translation message that will be substituted with the corresponding value after
     * translation. The format for this is to use curly brackets around the parameter name as you can see in the following example:
     *
     * ```php
     * $username = 'Alexander';
     * echo \PG\I18N::t('app', 'Hello, {username}!', ['username' => $username]);
     * ```
     *
     * Further formatting of message parameters is supported using the [PHP intl extensions](http://www.php.net/manual/en/intro.intl.php)
     * message formatter. See [[\PG\I18N::translate()]] for more details.
     *
     * @param string $category the message category.
     * @param string $message the message to be translated.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-us`, `en`). If this is null, the current
     * [application language] will be used.
     * @return string the translated message.
     */
    public static function t($category, $message, $params = [], $language = null)
    {
        if (self::$i18n === null) {
            self::$i18n = new self();
        }

        return self::$i18n->translate($category, $message, $params, $language ?: 'en_us');
    }

    /**
     * Initializes the component by configuring the default message categories.
     * @param array $config 配置，如：
     * [
     *     'class' => 'PhpMessageSource',
     *     'sourceLanguage' => 'en_us',
     *     'basePath' => '<DIR>/messages', // 翻译配置文件路径
     * ]
     */
    public function __construct(array $config)
    {
        if (! isset($this->translations['app']) && ! isset($this->translations['app*'])) {
            $this->translations['app'] = $config;
        }
    }

    /**
     * Translates a message to the specified language.
     *
     * After translation the message will be formatted using [[MessageFormatter]] if it contains
     * ICU message format and `$params` are not empty.
     *
     * @param string $category the message category.
     * @param string $message the message to be translated.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-us`, `en`).
     * @return string the translated and formatted message.
     */
    public function translate($category, $message, $params, $language)
    {
        $messageSource = $this->getMessageSource($category);
        $translation = $messageSource->translate($category, $message, $language);
        if ($translation === false) {
            return $this->format($message, $params, $messageSource->sourceLanguage);
        } else {
            return $this->format($translation, $params, $language);
        }
    }

    /**
     * Formats a message using [[MessageFormatter]].
     *
     * @param string $message the message to be formatted.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the formatted message.
     */
    public function format($message, $params, $language)
    {
        $params = (array)$params;
        if ($params === []) {
            return $message;
        }

        if (preg_match('~{\s*[\d\w]+\s*,~u', $message)) {
            $formatter = $this->getMessageFormatter();
            $result = $formatter->format($message, $params, $language);
            if ($result === false) {
                // $errorMessage = $formatter->getErrorMessage();
                return $message;
            } else {
                return $result;
            }
        }

        $p = [];
        foreach ($params as $name => $value) {
            $p['{' . $name . '}'] = $value;
        }

        return strtr($message, $p);
    }

    /**
     * @var string|array|MessageFormatter
     */
    private $_messageFormatter;

    /**
     * Returns the message formatter instance.
     * @return MessageFormatter the message formatter to be used to format message via ICU message format.
     */
    public function getMessageFormatter()
    {
        if ($this->_messageFormatter === null) {
            $this->_messageFormatter = new MessageFormatter();
        } elseif (is_array($this->_messageFormatter) || is_string($this->_messageFormatter)) {
            $this->_messageFormatter = self::createObject($this->_messageFormatter);
        }

        return $this->_messageFormatter;
    }

    /**
     * @param string|array|MessageFormatter $value the message formatter to be used to format message via ICU message format.
     * Can be given as array or string configuration that will be given to [[self::createObject]] to create an instance
     * or a [[MessageFormatter]] instance.
     */
    public function setMessageFormatter($value)
    {
        $this->_messageFormatter = $value;
    }

    /**
     * Returns the message source for the given category.
     * @param string $category the category name.
     * @return MessageSource the message source for the given category.
     * @throws InvalidConfigException if there is no message source available for the specified category.
     */
    public function getMessageSource($category)
    {
        if (isset($this->translations[$category])) {
            $source = $this->translations[$category];
            if ($source instanceof MessageSource) {
                return $source;
            } else {
                return $this->translations[$category] = self::createObject($source);
            }
        } else {
            // try wildcard matching 前缀通配符方式
            foreach ($this->translations as $pattern => $source) {
                if (strpos($pattern, '*') > 0 && strpos($category, rtrim($pattern, '*')) === 0) {
                    if ($source instanceof MessageSource) {
                        return $source;
                    } else {
                        return $this->translations[$category] = $this->translations[$pattern] = self::createObject($source);
                    }
                }
            }
            // match '*' in the last
            if (isset($this->translations['*'])) {
                $source = $this->translations['*'];
                if ($source instanceof MessageSource) {
                    return $source;
                } else {
                    return $this->translations[$category] = $this->translations['*'] = self::createObject($source);
                }
            }
        }

        throw new \Exception("Unable to locate message source for category '$category'.");
    }

    /**
     * Creates a new object using the given configuration.
     *
     * The method supports creating an object based on a class name, a configuration array or
     * an anonymous function.
     *
     * Below are some usage examples:
     *
     * ```php
     * // create an object using a class name
     * $object = self::createObject('PhpMessageSource');
     *
     * // create an object using a configuration array
     * $object = self::createObject([
     *     'class' => 'PhpMessageSource',
     *     'sourceLanguage' => 'PhpMessageSource',
     *     'basePath' => '@app/messages'
     * ]);
     *
     * // create an object with two constructor parameters
     * $object = self::createObject('MyClass', [$param1, $param2]);
     * ```
     *
     * @param string|array|callable $type the object type. This can be specified in one of the following forms:
     *
     * - a string: representing the class name of the object to be created
     * - a configuration array: the array must contain a `class` element which is treated as the object class,
     *   and the rest of the name-value pairs will be used to initialize the corresponding object properties
     * - a PHP callable: either an anonymous function or an array representing a class method (`[$class or $object, $method]`).
     *   The callable should return a new instance of the object being created.
     *
     * @param array $params the constructor parameters
     * @return object the created object
     * @throws Exception if the configuration is invalid.
     */
    public static function createObject($type, array $params = [])
    {
        if (is_string($type)) {
            return new $type;
        } elseif (is_array($type) && isset($type['class'])) {
            $class = $type['class'];
            unset($type['class']);
            $clazz = new $class;
            foreach ($type as $prop => $val) {
                $clazz->$prop = $val;
            }
        } elseif (is_array($type)) {
            throw new \Exception('Object configuration must be an array containing a "class" element.');
        }

        throw new \Exception('Unsupported configuration type: ' . gettype($type));
    }
}
