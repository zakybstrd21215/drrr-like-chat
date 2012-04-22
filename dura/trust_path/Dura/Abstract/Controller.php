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

abstract class Dura_Abstract_Controller
{
	protected $output = array();
	protected $template = null;

	public function __construct()
	{
	}

	public function main()
	{
		// bluelovers
		if (!empty(Dura::$action))
		{
			$_method = '_main_action_' . Dura::$action;

			if (method_exists($this, $_method))
			{
				$this->$_method();
			}
		}
		// bluelovers
	}

	function _getTplFile($template)
	{
		$t = str_replace(DURA_TEMPLATE_PATH, DURA_TEMPLATE_PATH.'/../tpl/', $template);

		if (file_exists($t))
		{
			$template = $t;
		}

		return $template;
	}

	protected function _view()
	{
		if (!$this->template)
		{
			$this->template = DURA_TEMPLATE_PATH . '/' . Dura::$controller . '.' . Dura::$action . '.php';
		}

		// debug new tpl
		$this->template = $this->_getTplFile($this->template);

		$this->_escapeHtml($this->output);

		/*
		ob_start();
		$this->_display($this->output);
		$content = ob_get_contents();
		ob_end_clean();
		*/
		$content = Dura_Abstract_View::render($this->output, $this->template);

		$this->_render($content, $this->output);
	}

	protected function _display($dura)
	{
		require $this->template;
	}

	protected function _render($content, $dura)
	{
		require $this->_getTplFile(DURA_TEMPLATE_PATH . '/theme.php');
	}

	protected function _validateUser()
	{
		if (!Dura::user()->isUser())
		{
			Dura::redirect();
		}
	}

	protected function _validateAdmin()
	{
		if (!Dura::user()->isAdmin())
		{
			Dura::redirect();
		}
	}

	protected function _escapeHtml(&$vars)
	{
		foreach ($vars as $key => &$var)
		{
			if (is_array($var))
			{
				$this->_escapeHtml($var);
			}
			elseif (!is_object($var))
			{
				$var = Dura::escapeHtml($var);
			}
		}
	}
}


?>