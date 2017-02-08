<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_j2store
 * @subpackage 	Trangell_Mellat
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');
?>
<form action="https://bpm.shaparak.ir/pgwchannel/startpay.mellat" method="post" name="adminForm" target="_self">
	<p><?php echo 'درگاه بانک ملت' ?></p>
	<input type="submit" class="j2store_cart_button button btn btn-primary" value="<?php echo JText::_($vars->button_text); ?>" />
    <input type='hidden' name='RefId' value='<?php echo @$vars->trangellmellat; ?>'>
	<br />
</form>