<?php

/**
 * A simple description for this script
 *
 * PHP Version 5.2.0 or Upper version
 *
 * @package    Dura
 * @author     Hidehito NOZAWA aka Suin <http://suin.asia>
 * @copyright  2010 Hidehito NOZAWA
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3
 *
 */

class Dura_Controller_CreateRoom extends Dura_Abstract_Controller
{
	protected $error = null;
	protected $input = null;

	protected $userMax = null;
	protected $languages = array();

	public $allowActions = array(
		Dura::DEFAULT_ACTION,
	);

	public function __construct()
	{
		parent::__construct();
	}

	function _main_before()
	{
		$this->_validateUser();

		$this->_redirectToRoom();
	}

	public function _main_action_default()
	{
		$this->_languages();

		$this->_roomLimit();

		$this->_getInput();

		if (Dura::request('name') || Dura::request('submit'))
		{
			ob_start();

			try
			{
				$this->_create();
			}
			catch (Exception $e)
			{
				$this->error[] = $e->getMessage();
			}

			ob_end_clean();
		}

		$this->_default();
	}

	protected function _redirectToRoom()
	{
		if (Dura_Model_Room_Session::isCreated())
		{
			Dura::redirect('room');
		}
	}

	protected function _getInput()
	{
		$this->input['name'] = Dura::request('name', '', true);
		$this->input['limit'] = Dura::request('limit');
		$this->input['language'] = Dura::request('language');
		$this->input['name'] = trim($this->input['name']);
		$this->input['language'] = trim($this->input['language']);

		// bluelovers
		$this->input['password'] = Dura::request('password', 0, true);
		$this->input['password'] = trim($this->input['password']);
		$this->input['password'] = $this->input['password'] ? $this->input['password'] : 0;

		if (empty($this->input['language']))
		{
			$this->input['language'] = Dura::user()->getLanguage();
		}

		if (empty($this->input['limit']))
		{
			$this->input['limit'] = max(DURA_USER_MIN, intval(max($this->userMax, DURA_USER_MIN) / 2));
		}
		// bluelovers
	}

	protected function _default()
	{
		$this->output['user_min'] = DURA_USER_MIN;
		$this->output['user_max'] = $this->userMax;
		$this->output['languages'] = $this->languages;
		$this->output['input'] = $this->input;
		$this->output['error'] = $this->error;
		$this->_view();
	}

	protected function _create()
	{
		$this->_validate();

		$this->_createRoom();
	}

	protected function _validate()
	{
		$name = $this->input['name'];

		if ($name === '')
		{
			throw new Exception(t("Please input name."));
		}

		if (mb_strlen($name) > 10)
		{
			throw new Exception(t("Name should be less than 10 letters."));
		}

		$limit = $this->input['limit'];

		if ($limit < DURA_USER_MIN)
		{
			throw new Exception(t("Member should be more than {1}.", DURA_USER_MIN));
		}

		if ($limit > $this->userMax)
		{
			throw new Exception(t("Member should be less than {1}.", $this->userMax));
		}

		if (!in_array($this->input['language'], array_keys($this->languages)))
		{
			throw new Exception(t("The language is not in the option."));
		}
	}

	protected function _roomLimit()
	{
		$roomHandler = new Dura_Model_Room_XmlHandler;
		$roomModels = $roomHandler->loadAll();

		$roomExpire = time() - DURA_CHAT_ROOM_EXPIRE;

		$usedCapacity = 0;

		foreach ($roomModels as $id => $roomModel)
		{
			if (intval($roomModel->update) < $roomExpire)
			{
				$roomHandler->delete($id);
				continue;
			}

			$usedCapacity += (int)$roomModel->limit;
		}

		unset($roomHandler, $roomModels, $roomModel);

		if ($usedCapacity >= DURA_SITE_USER_CAPACITY)
		{
			Dura::trans(t("Cannot create new room any more."), 'lounge');
		}

		$this->userMax = DURA_SITE_USER_CAPACITY - $usedCapacity;

		if ($this->userMax > DURA_USER_MAX)
		{
			$this->userMax = DURA_USER_MAX;
		}

		if ($this->userMax < DURA_USER_MIN)
		{
			Dura::trans(t("Cannot create new room any more."), 'lounge');
		}
	}

	protected function _createRoom()
	{
		$userName = Dura::user()->getName();
		$userId = Dura::user()->getId();
		$userIcon = Dura::user()->getIcon();

		/*
		$roomHandler = new Dura_Model_Room_XmlHandler;
		$roomModel = $roomHandler->create();
		*/

		$_room = new Dura_Model_Room;

		$_room->create();

		$roomHandler = &$_room->roomHandler;
		$roomModel = &$_room->roomModel;

		$roomModel->name = $this->input['name'];
		$roomModel->update = time();
		$roomModel->limit = $this->input['limit'];
		/*
		$roomModel->host = $userId;
		*/
		$roomModel->language = $this->input['language'];

		// bluelovers
		$roomHandler->setPassword($roomModel, $this->input['password']);
		// bluelovers

		/*
		$users = $roomModel->addChild('users');
		$users->addChild('name', $userName);
		$users->addChild('id', $userId);
		$users->addChild('icon', $userIcon);
		$users->addChild('update', time());
		*/

		if (Dura::$language != $this->input['language'])
		{
			$langFile = DURA_TRUST_PATH . '/language/' . $this->input['language'] . '.php';
			Dura::$catalog = require $langFile;
		}

		/*
		$talk = $roomModel->addChild('talks');
		$talk->addChild('id', md5(microtime() . mt_rand()));
		$talk->addChild('uid', 0);
		$talk->addChild('name', $userName);
		$talk->addChild('message', "{1} logged in.");
		$talk->addChild('icon', '');
		$talk->addChild('time', time());

		$id = md5(microtime() . mt_rand());
		*/

		$_room->addUser(Dura::user(), true);
		$_room->setHost(Dura::user());

		if (!$_room->save())
		{
			throw new Exception(t("Data Error: Room creating failed."));
		}

		Dura_Model_Room_Session::create($id);

		// bluelovers
		Dura_Model_Room_Session::updateUserSesstion($roomModel, Dura::user());
		// bluelovers

		Dura::redirect('room');
	}

	protected function _languages()
	{
		require_once DURA_TRUST_PATH . '/language/list.php';

		$languages = dura_get_language_list();

		foreach ($languages as $langcode => $name)
		{
			if (!file_exists(DURA_TRUST_PATH . '/language/' . $langcode . '.php'))
			{
				unset($languages[$langcode]);
			}
		}

		$this->languages = $languages;
	}
}


?>