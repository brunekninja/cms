<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\models;

use Craft;
use craft\base\Model;
use craft\validators\SingleSectionUriValidator;
use craft\validators\SiteIdValidator;
use craft\validators\UriFormatValidator;
use yii\base\InvalidConfigException;

/**
 * Section_SiteSettings model class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class Section_SiteSettings extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Section ID
     */
    public $sectionId;

    /**
     * @var int Site ID
     */
    public $siteId;

    /**
     * @var bool Enabled by default
     */
    public $enabledByDefault = true;

    /**
     * @var bool Has URLs?
     */
    public $hasUrls;

    /**
     * @var string URI format
     */
    public $uriFormat;

    /**
     * @var string Entry template
     */
    public $template;

    /**
     * @var Section
     */
    private $_section;

    // Public Methods
    // =========================================================================

    /**
     * Returns the section.
     *
     * @return Section
     * @throws InvalidConfigException if [[sectionId]] is missing or invalid
     */
    public function getSection(): Section
    {
        if ($this->_section !== null) {
            return $this->_section;
        }

        if (!$this->sectionId) {
            throw new InvalidConfigException('Section site settings model is missing its section ID');
        }

        if (($this->_section = Craft::$app->getSections()->getSectionById($this->sectionId)) === null) {
            throw new InvalidConfigException('Invalid section ID: '.$this->sectionId);
        }

        return $this->_section;
    }

    /**
     * Sets the section.
     *
     * @param Section $section
     *
     * @return void
     */
    public function setSection(Section $section)
    {
        $this->_section = $section;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        $labels = [
            'template' => Craft::t('app', 'Template'),
        ];

        if ($this->getSection()->type == Section::TYPE_SINGLE) {
            $labels['uriFormat'] = Craft::t('app', 'URI');
        } else {
            $labels['uriFormat'] = Craft::t('app', 'Entry URI Format');
        }

        return $labels;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = [
            [['id', 'sectionId', 'siteId'], 'number', 'integerOnly' => true],
            [['siteId'], SiteIdValidator::class],
            [['template'], 'string', 'max' => 500],
        ];

        if ($this->getSection()->type == Section::TYPE_SINGLE) {
            $rules[] = ['uriFormat', SingleSectionUriValidator::class];
        } else {
            $rules[] = ['uriFormat', UriFormatValidator::class];
        }

        if ($this->hasUrls || $this->getSection()->type == Section::TYPE_SINGLE) {
            $rules[] = [['uriFormat'], 'required'];
        }

        return $rules;
    }
}
