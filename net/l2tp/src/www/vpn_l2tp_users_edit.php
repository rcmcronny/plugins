<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2006 Scott Ullrich <sullrich@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

function l2tpusercmp($a, $b)
{
    return  strcasecmp($a['name'], $b['name']);
}

function l2tp_users_sort()
{
    global  $config;

    if (!is_array($config['l2tp']['user'])) {
        return;
    }

    usort($config['l2tp']['user'], "l2tpusercmp");
}

require_once("guiconfig.inc");
require_once("system.inc");
require_once("plugins.inc.d/if_l2tp.inc");

$a_secret = &config_read_array('l2tp', 'user');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_secret[$_GET['id']])) {
        $id = $_GET['id'];
    }
    if (isset($id)) {
        $pconfig['usernamefld'] = $a_secret[$id]['name'];
        $pconfig['ip'] = $a_secret[$id]['ip'];
    } else {
        $pconfig['usernamefld'] = null;
        $pconfig['ip'] = null;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_secret[$_POST['id']])) {
        $id = $_POST['id'];
    }
    unset($input_errors);
    $pconfig = $_POST;

    /* input validation */
    if (isset($id) && ($a_secret[$id])) {
        $reqdfields = explode(" ", "usernamefld");
        $reqdfieldsn = array(gettext("Username"));
    } else {
        $reqdfields = explode(" ", "usernamefld passwordfld");
        $reqdfieldsn = array(gettext("Username"),gettext("Password"));
    }

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['usernamefld'])) {
        $input_errors[] = gettext("The username contains invalid characters.");
    }

    if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['passwordfld'])) {
        $input_errors[] = gettext("The password contains invalid characters.");
    }

    if (($_POST['passwordfld']) && ($_POST['passwordfld'] != $_POST['password2'])) {
        $input_errors[] = gettext("The passwords do not match.");
    }
    if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) {
        $input_errors[] = gettext("The IP address entered is not valid.");
    }

    if (!$input_errors && !(isset($id) && $a_secret[$id])) {
        /* make sure there are no dupes */
        foreach ($a_secret as $secretent) {
            if ($secretent['name'] == $_POST['usernamefld']) {
                $input_errors[] = gettext("Another entry with the same username already exists.");
                break;
            }
        }
    }

    if (!$input_errors) {
        if (isset($id) && $a_secret[$id]) {
            $secretent = $a_secret[$id];
        }

        $secretent['name'] = $_POST['usernamefld'];
        $secretent['ip'] = $_POST['ip'];

        if ($_POST['passwordfld']) {
            $secretent['password'] = $_POST['passwordfld'];
        }

        if (isset($id) && $a_secret[$id]) {
            $a_secret[$id] = $secretent;
        } else {
            $a_secret[] = $secretent;
        }

        l2tp_users_sort();
        write_config();
        if_l2tp_configure_do();
        header(url_safe('Location: /vpn_l2tp_users.php'));
        exit;
    }
}

$service_hook = 'l2tpd';
legacy_html_escape_form_data($pconfig);
include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">

<?php
      if (isset($input_errors) && count($input_errors) > 0) {
          print_input_errors($input_errors);
      } ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
              <form method="post" name="iform" id="iform">
               <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td style="width:22%">
                      <strong><?=gettext("Edit User");?></strong>
                    </td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Username");?></td>
                    <td>
                      <input name="usernamefld" type="text" value="<?=$pconfig['usernamefld'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Password");?></td>
                    <td>
                      <input name="passwordfld" type="password" /><br />
                      <input name="password2" type="password" />
                      &nbsp;(<?=gettext("confirmation");?>)
                      <?php if (isset($id)):?><br />
                      <div class="text-muted"><em><small><?=gettext("If you want to change the users password, enter it here twice.");?></small></em></div>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_ip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP address");?></td>
                    <td>
                      <input name="ip" type="text" value="<?=$pconfig['ip'];?>" />
                      <div class="hidden" data-for="help_for_ip">
                        <?=gettext("If you want the user to be assigned a specific IP address, enter it here.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input id="submit" name="Submit" type="submit" class="btn btn-primary" value="<?=gettext('Save');?>" />
                      <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/vpn_l2tp_users.php');?>'" />
                      <?php if (isset($id)) :?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </table>
               </div>
              </form>
            </div>
          </section>
        </div>
      </div>
    </section>
<?php include("foot.inc");
