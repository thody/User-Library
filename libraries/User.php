<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * User Class
 *
 * @package		User
 * @category	User Library
 * @author		Adam Thody
 * @link		http://www.adamthody.com
 * @version		0.3
 */
class User {

	// Private variables
	var $CI;
	
	/**
	 * User Class Constructor
	 */		
	function User($params = array())
	{	
		// Instantiate CI
		$this->CI =& get_instance();
		
		// Load User class config
		$this->CI->load->config('user');
		
		// Set adapter from config
		$adapter_class = $this->CI->config->item('user_adapter') ? $this->CI->config->item('user_adapter') : 'user_db';
		$this->CI->load->library('user/adapters/' . $adapter_class, null, 'user_adapter');
		
		log_message('debug', "User Class Initialized");
	}

	// --------------------------------------------------------------------
	
	/**
	 * Insert user into the users table
	 *
	 * @access	public
	 * @param	array	$user
	 * @return	bool
	 */
	function create($user = array())
	{
		return $this->CI->user_adapter->create($user);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Update user in the users table
	 *
	 * @access	public
	 * @param	string	$user_id
	 * @param	array	$user
	 * @return	bool
	 */
	function update($user_id, $user = array())
	{
		return $this->CI->user_adapter->update($user_id, $user);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Delete user from the users table
	 *
	 * @access	public
	 * @param	string	$identifier		Can be user's ID or user's username
	 * @return	bool
	 */
	function delete($identifier)
	{
		return $this->CI->user_adapter->delete($identifier);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Login user
	 *
	 * @access	public
	 * @param	string	$username
	 * @param	string	$password	Unencrypted version 
	 * @param	bool	$persistent	
	 * @return	bool
	 */
	function login($identity = FALSE, $password = FALSE, $persistent = FALSE)
	{
		return $this->CI->user_adapter->login($identity, $password, $persistent);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Checks for a persistent user session
	 *
	 * @access	public
	 * @return	bool
	 */
	function is_persistent_session()
	{
		return $this->CI->user_adapter->is_persistent_session();
	}
	
	// --------------------------------------------------------------------

	/**
	 * Logout current user
	 *
	 * @access	public
	 * @return	bool
	 */
	function logout()
	{	
		return $this->CI->user_adapter->logout();
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Check if user is logged in
	 *
	 * @access	public
	 * @return	bool
	 */
	function is_logged_in()
	{
		return $this->CI->user_adapter->is_logged_in();
	}
	
	// --------------------------------------------------------------------

	/**
	 * Gets user attributes
	 *
	 * @access public
	 * @param string	$attr_to_get
	 * @return string
	 */	
	function get_attr($attr_to_get)
	{
		return $this->CI->user_adapter->get_attr($attr_to_get);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Sets user attributes
	 *
	 * @access public
	 * @param string	$attr_to_set
	 * @param string	$value
	 * @return boolean
	 */	
	
	function set_attr($attr_to_set, $value)
	{
		return $this->CI->user_adapter->set_attr($attr_to_set, $value);
	}
		
	// --------------------------------------------------------------------

	/**
	 * Checks if a username is already in the database
	 *
	 * @access public
	 * @param string	$username
	 * @return boolean
	 */
	function is_available_username($username)
	{
		return $this->CI->user_adapter->is_available_username($username);
	}
}
// END User Class

/* End of file User.php */
/* Location: ./system/application/libraries/User.php */