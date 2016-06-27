<?php 
/**
 * Emails that are common to all forms
 */


/**
 * Method Name : paymentRefund
 * Desc : The purpose of this email is to inform 
 * the user that arecont has refunded the 
 * amount they have charged for missing or 
 * damaged .
 *
 * @access public
 * @param  Int    $id  Id of the form.
 * @param  Int    $pid Id of the payment being  refunded.
 * refunded.
 * @param  Object $dbh PDO connection object.
 *
 * @return void
 */
function paymentRefund($id, $pid, $dbh) 
{
    /* BEGIN: Set Defaults */
    include ROOT_DIR.'/includes/email_setting.php';
    $to = array();
    $cc = array();
    $bcc = array();
    $form_type = '';
    $s3 = '';
    $email_cc = null;
    $email_bcc = null;
    $email_status = '';
    $zz_created_ts = date('Y-m-d H:i:s');
    $zz_created_by = null;
    /* END: Set Defaults */

    /* BEGIN: Get form Information */
    $sql = trim(
        "
                SELECT 
                    contactFirstName, contactLastName, contactEmail, c.member_id, formType,
                    (SELECT sp.sales_firstname FROM sales_person sp WHERE c.assignedIsmId = sp.member_id AND sp.member_id > 0 LIMIT 1 ) AS sales_firstname,
                FROM channel_form c 
                WHERE c.id = :id
                ORDER BY created_ts desc
                LIMIT 1
            "
    );
        $mailArray = array("id" => $id, "pid" => $pid);
        $mailPrepare = $dbh->prepare($sql);

    try {
        $mailPrepare->execute($mailArray);
    } catch (PDOException $e) {
        echo 'Error During fetching Channel Support Form information: <br>'.$e->getMessage();
        exit;
    }
        $row_count = $mailPrepare->rowCount();
        $row =  $mailPrepare->fetchObject();
       
    if ($row_count >= 1) {
        $user_email = $row->contactEmail;
        $zz_created_by = $row->member_id;

        $sales_firstname = $row->sales_firstname;
        $sales_lastname = $row->sales_lastname;
        $sales_email = $row->sales_email;
        $sales_phone = $row->sales_phone;

        if (!empty($row->sales_email)) {
            $cc = array($row->sales_email);
        } else {
            $cc = array($emailISM);
            $sales_email = $emailISM;
        }
        
        $form_type = getFormTypeStr($row->formType);
        $email_subject = 'Arecont Vision ['.$form_type.'] #' . $id . ' charge has been refunded';

        $s3  = '<p>Hello' . ' ' . $row->contactFirstName . ' ' . $row->contactLastName . ',</p>';
        $s3 .= '<p>We have refunded the ' . $row->created_ts . ' charge for the damaged or missing product(s) from
        your '.$form_type.' #' . $id . '</p>';

        $s3 .= '<p>If you would like to talk to an ISM about this matter, or if you have any additional questions please contact your ISM.</p>'; 
        $s3 .= '<h3>ISM Contact</h3>';
        $s3 .=  '<table cellspacing="0" cellpadding="5" border="0">';
        $s3 .= '<tr><td>Name:</td>'; 
        $s3 .= '<td>' . $row->sales_firstname . ' ' . $row->sales_lastname . '</td></tr>';
        $s3 .= '<tr><td>Email:</td>'; 
        $s3 .= '<td>' . $row->sales_email . '</td></tr>';
                
        if (!empty($row->sales_phone)) {
            $s3 .= '<tr><td>Phone:</td>';
            $s3 .= '<td>' . $row->sales_phone . '</td></tr>';
        }

        $s3 .= '</table>';
        $s3 .= '<hr />';
        $s3 .= '<p><a href="' . HOSTURL . 
        '/channel-support/view.php?id=' . $id . '" target="_blank">
        Click here to review your '.$form_type.' application.</a></p>';

        if (HOSTENV == 'development') {
            // On Development, do not send emails except for people added in this section.
            $to = $emailDev;
            $cc = array();
            $bcc = array();
            $email_subject = '[DEV SERVER] '. $email_subject;
        } else {
            $to = array($user_email);
        }
        
        $content = file_get_contents(MAILTEMPLATEPATH);
        $html_message = str_replace('{{CONTENT}}', $s3, $content);
        $html_message = str_replace('{{PATH}}', MAILTEMPLATEURL, $html_message);
        $html_message = str_replace('{{FormName}}', $form_type, $html_message);
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
}

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
