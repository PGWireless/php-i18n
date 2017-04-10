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
     * @var array
     */
    public $translations;

    /**
     * 实例化
     * @param array $config 配置，如：
     * [
     *     'class' => 'PhpMessageSource',
     *     'sourceLanguage' => 'en_us',
     *     'basePath' => '<DIR>/Languages', // 翻译配置文件路径
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
     * 释放实例，使得可以重新 new 出新对象
     */
    public static function releaseInstance()
    {
        self::$i18n = null;
    }

    /**
     * @param string $category
     * @param string $message
     * @param array $params
     * @param null | string $language
     * @return mixed
     */
    public static function t($category, $message, $params = [], $language = null)
    {
        return self::$i18n->translate($category, $message, $params, $language ?: 'en_us');
    }

    /**
     * Initializes the component by configuring the default message categories.
     * @param array $config 配置，如：
     * [
     *     'class' => 'PhpMessageSource',
     *     'sourceLanguage' => 'en_us',
     *     'basePath' => '<DIR>/Languages', // 翻译配置文件路径
     *     'fileMap' => [
     *         'common' => 'common.php',
     *         'error' => 'error.php'
     *     ]
     * ]
     */
    public function __construct(array $config)
    {
        $this->translations['*'] = $config;
    }

    /**
     * @param string $category
     * @param string $message
     * @param array $params
     * @param string $language
     * @return string
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
        if (isset($this->translations['*'])) {
            $source = $this->translations['*'];
            if ($source instanceof MessageSource) {
                return $source;
            } else {
                return $this->translations[$category] = $this->translations['*'] = static::createObject($source);
            }
        }

        throw new \Exception("Unable to locate message source for category '$category'.");
    }

    /**
     * 创建对象
     * @param mixed $type
     * @param array $params
     * @return mixed
     * @throws \Exception
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
            return $clazz;
        } elseif (is_array($type)) {
            throw new \Exception('Object configuration must be an array containing a "class" element.');
        }

        throw new \Exception('Unsupported configuration type: ' . gettype($type));
    }
}
