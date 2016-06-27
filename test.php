<?php 
/**
 * Emails that are common to all forms
 */


/**
 * Method Name : signup_email
 * Desc : The purpose of this email is to inform the user 
 * that a new member account created for them 
 *
 * @access public
 * @param  Object $dbh        PDO connection object.
 * @param  Int    $id         Id of the form.
 * @param  Array  $f          Array containing user details.
 * @param  String $password   Password of user.
 * @param  Int    $creator_id Member id of the creator of the form.
 *
 * @return void
 */
function signup_email($dbh, $id, $f, $password, $creator_id) 
{
    /* BEGIN: Set Defaults */
    include ROOT_DIR.'/includes/email_setting.php';
    $to = array();
    $cc = array();
    $bcc = array();
    $message = '';
    $email_cc = null;
    $email_bcc = null;
    $email_status = '';
    $zz_created_ts = date('Y-m-d H:i:s');
    $zz_created_by = $creator_id;
    $email_subject = 'Welcome to Arecont Vision Partner Program';
    /* END: Set Defaults */

    /* BEGIN: Create message */
    $message = '<p>Dear ' . $f['contactFirstName'] . ' ' . $f['contactLastName'] . ', </p>';   
    $message .= '<p>Welcome and thank you for signing 
    up to be a part of Arecont Vision Partner Programs.</p>';
    $message .= '<p> You have just taken the first step in joining a new and exciting venture. Your Arecont Vision Partner Portal username and password are listed below. Please retain a copy for your records.</p>';
    $message .= '<p>Username: '.$f['contactEmail'].' <br>Password: '.$password.' </p>';
    $message .= '<p>Please sign in to our ';
    $message .= '<a href="' . HOSTURL . 
                '/signin.php" target="_blank">
                Partner Portal </a></p>';
    $message .= "<p>Thank you again for joining Arecont Vision's Partner Programs.</p>";
    /* END: Create message */

    if (HOSTENV == 'development') {
        // On Development, do not send emails except for people added in this section.
        $to = $emailDev;
        $cc = array();
        $bcc = array();
        $email_subject = '[DEV SERVER] '. $email_subject;
    } else {
        $to = array($f['contactEmail']);
    }
        
        $content = file_get_contents(MAILTEMPLATEPATH);
        $html_message = str_replace('{{CONTENT}}', $message, $content);
        $html_message = str_replace('{{PATH}}', MAILTEMPLATEURL, $html_message);
        $html_message = str_replace('{{FormName}}', '', $html_message);
        $mail->Subject =  $email_subject;
        $mail->MsgHTML($html_message);
        
    if (count($to) > 0) {
        foreach ($to as $t) {
            $mail->AddAddress(trim($t));
        }
        $email_to = implode(", ", $to);
    }
      
    if (count($cc) > 0) {
        foreach ($cc as $c) {
            $mail->AddCC($c);
        }
        $email_cc = implode(", ", $cc);
    }
      
    if (count($bcc) > 0) {
        foreach ($bcc as $c) {
            $mail->AddBCC($c);
        }
        $email_bcc = implode(", ", $bcc);
    }
        
    if (!$mail->Send()) {
        $email_status = $mail->ErrorInfo;
    } else {
        $email_status = 'Success';
    }
        $mail->ClearAddresses();
        
        save_email($dbh, $id, $email_to, $email_cc, $email_bcc, $email_subject, $html_message, $zz_created_ts, $zz_created_by, $email_status);
}
?>
