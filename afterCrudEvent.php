<?php
/**
 * Created by PhpStorm.
 * User: aschuurman
 * Date: 19/07/2017
 * Time: 13:22
 */
namespace drsdre\radtools;

use yii\base\Event;

/**
 * afterCrudEvent represents the event parameter used for a crud action event.
 *
 * @author Andre Schuurman
 * @since 2.0
 */
class afterCrudEvent extends Event
{
	/**
	 * @var Action the action currently being executed
	 */
	public $action;

	/**
	 * @var  yii\db\ActiveRecord the resulting crud model.
	 */
	public $model;

	/**
	 * @var mixed result paramters for action.
	 */
	public $result;

	/**
	 * Constructor.
	 * @param Action $action the action associated with this action event.
	 * @param array $config name-value pairs that will be used to initialize the object properties
	 */
	public function __construct(string $action, $config = [])
	{
		$this->action = $action;
		parent::__construct($config);
	}
}