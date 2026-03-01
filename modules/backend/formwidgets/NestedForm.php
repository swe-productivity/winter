<?php namespace Backend\FormWidgets;

use Backend\Classes\FormWidgetBase;
use Backend\Widgets\Form;
use Winter\Storm\Database\Model;

/**
 * Nested Form
 * Renders a nested form bound to a jsonable field of a model.
 *
 * @package winter\wn-backend-module
 * @author Sascha Aeppli
 */
class NestedForm extends FormWidgetBase
{
    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'nestedform';

    /**
     * @var array Form configuration
     */
    public $form;

    /**
     * @var bool defines if the nested form is styled like a panel (default true).
     */
    public $usePanelStyles = true;

    /**
     * @var Form form widget reference
     */
    protected $formWidget;

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->fillFromConfig([
            'form',
            'usePanelStyles',
        ]);

        if ($this->formField->disabled) {
            $this->previewMode = true;
        }

        $nestedData = $this->getLoadValue();
        
        $dummyModel = new class extends Model {
            public function __get($key) {
                return null;
            }
            public function __isset($key) {
                return false;
            }
            public function exists() {
                return false;
            }
        };
        
        $config = $this->makeConfig($this->form);
        $config->model = $dummyModel;
        $config->data = $nestedData ?: [];
        $config->alias = $this->alias . $this->defaultAlias;
        $config->arrayName = $this->getFieldName();
        $config->isNested = true;

        if (object_get($this->getParentForm()->config, 'enableDefaults') === true) {
            $config->enableDefaults = true;
        }

        $widget = $this->makeWidget(Form::class, $config);
        $widget->previewMode = $this->previewMode;
        $widget->bindToController();

        $this->formWidget = $widget;
    }

    protected function loadAssets()
    {
        $this->addCss('css/nestedform.css', 'core');
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('nestedform');
    }

    public function prepareVars()
    {
        $this->formWidget->previewMode = $this->previewMode;
    }
}
