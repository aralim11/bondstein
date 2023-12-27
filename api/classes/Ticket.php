<?php 
/**
 * Ticket class
 * All ticket methods here
 */
require_once('Functions.php');

class Ticket extends Functions
{
    /**
     * ticketList
     * @return list
     */
    public function ticketList($request){
        ## validate request method
        $validatePost = $this->validatePostRequest($_SERVER['REQUEST_METHOD']);
        if ($validatePost) {
            ## validate content-type
            $validateContentType = $this->validateContentType($_SERVER["CONTENT_TYPE"]);
            if ($validateContentType) {
                ## validate token
                $validateToken = $this->validateToken(apache_request_headers());
                if ($validateToken['status']) {
                    ## check empty for request
                    if (!empty($request->authorized_by) && !empty($request->username) && !empty($request->role)) {
                        ## user_name join query
                        $userNameJoin = "IF(webhook_ticket.data_type = 'message', (SELECT webhook_messages.sender_name FROM webhook_messages WHERE webhook_messages.unique_id = webhook_ticket.unique_id AND webhook_messages.sender_id = webhook_ticket.comment_id LIMIT 1), (SELECT webhook_comments.user_name FROM webhook_comments WHERE webhook_comments.unique_id = webhook_ticket.unique_id AND webhook_comments.comment_id = webhook_ticket.comment_id LIMIT 1)) AS user_name";

                        ## fetch unsolved ticket
                        if ($request->role == 'User') {
                            $ticketResult = $this->connection->query("SELECT page.name, page.page_id, webhook_ticket.data_type, webhook_ticket.unique_id, webhook_ticket.comment_id, webhook_ticket.status, $userNameJoin FROM webhook_ticket INNER JOIN page ON page.page_id = webhook_ticket.page_id WHERE webhook_ticket.authorized_by = '{$request->authorized_by}' AND webhook_ticket.assign_user = '{$request->username}'");
                        } else {
                            $ticketResult = $this->connection->query("SELECT page.name, page.page_id, webhook_ticket.data_type, webhook_ticket.unique_id, webhook_ticket.comment_id, webhook_ticket.status, $userNameJoin FROM webhook_ticket INNER JOIN page ON page.page_id = webhook_ticket.page_id WHERE webhook_ticket.authorized_by = '{$request->authorized_by}'");
                        }

                        ## check ticket found or not
                        if ($ticketResult->num_rows > 0) {
                            while ($ticketData = $ticketResult->fetch_assoc()) {
                                $json = array();
                                $json['user_name'] = $ticketData['user_name'];
                                $json['name'] = $ticketData['name'];
                                $json['data_type'] = $ticketData['data_type'];
                                $json['unique_id'] = $ticketData['unique_id'];
                                $json['comment_id'] = $ticketData['comment_id'];
                                $json['page_id'] = $ticketData['page_id'];
                                $json['status'] = $ticketData['status'];

                                $json_data[] = $json;
            
                                ## update seen status when ticket show on ticket list
                                $this->connection->query("UPDATE webhook_ticket SET seen_status = '1' WHERE unique_id = '{$ticketData[unique_id]}' AND authorized_by = '{$request->authorized_by}'");
                            }

                            ## return all ticket list
                            $this->throwMessage('200', $json_data);
                        }
                    } else {
                        ## return error message
                        $this->throwMessage('401', 'All Field Required!!');
                    }
                }
            }
        }
    }


    /**
     * showTicket
     * @param $request
     * @return mixed
     */
    public function showTicket($request){
        ## validate request method
        $validatePost = $this->validatePostRequest($_SERVER['REQUEST_METHOD']);
        if ($validatePost) {
            ## validate content-type
            $validateContentType = $this->validateContentType($_SERVER["CONTENT_TYPE"]);
            if ($validateContentType) {
                ## validate token
                $validateToken = $this->validateToken(apache_request_headers());
                if ($validateToken['status']) {
                    ## check empty for request
                    if (!empty($request->unique_id) && !empty($request->data_type) && !empty($request->authorized_by) && !empty($request->comment_id) && !empty($request->page_id)) {
                        if ($request->data_type == 'comment') {
                            ## fetch data for comment
                            $dataResult = $this->connection->query("SELECT webhook_comments.user_name, webhook_comments.message,  webhook_comments.comment_created_time, webhook_comments.user_id, webhook_comments.sender_type, webhook_atachments.attachment_url, webhook_atachments.attachment_type, webhook_comments.comment_id, webhook_comments.is_published, webhook_atachments.attachment_src FROM webhook_comments LEFT JOIN webhook_atachments ON webhook_comments.unique_id = webhook_atachments.unique_id WHERE (webhook_comments.comment_id = '{$request->comment_id}' OR webhook_comments.parent_id = '{$request->comment_id}') AND webhook_comments.authorized_by = '{$request->authorized_by}' AND webhook_comments.page_id = '{$request->page_id}' ORDER BY webhook_comments.id ASC");

                            ## set all comments data
                            while ($row_comments = $dataResult->fetch_assoc()) {
                                $json['user_name'] = $row_comments['user_name'];
                                $json['message'] = empty($row_comments['message']) ? "Unsupported Data &#x1F615; !!" : $row_comments['message']; 
                                $json['comment_created_time'] = ["date" => date("d M, Y",strtotime($row_comments['comment_created_time'])), "time" => date("h:i A",strtotime($row_comments['comment_created_time']))];
                                $json['user_id'] = $row_comments['user_id'];
                                $json['sender_type'] = $row_comments['sender_type'];
                                $json['is_published'] = $row_comments['is_published'];
                                $json['comment_id'] = $row_comments['comment_id'];
                                $json['attachment_type'] = $row_comments['attachment_type'];
                                $json['attachment_url'] = $row_comments['attachment_src'] != '' ? $this->baseUrl . 'img/download/' . $row_comments['attachment_src'] : '';
                                
                                $json_data[] = $json;
                            }

                            ## update seen status
			                $this->connection->query("UPDATE webhook_comments SET seen_status = 1 WHERE (comment_id = '{$request->comment_id}' OR parent_id = '{$request->comment_id}') AND authorized_by = '{$request->authorized_by}'");

                            ## get post_id by comment_id
                            $post_id_by_comment_id = $this->connection->query("SELECT post_id FROM webhook_comments WHERE unique_id = '{$request->unique_id}'")->fetch_assoc();

                            ## get post for this comments
                            $post_data = $this->connection->query("SELECT message, unique_id FROM webhook_posts WHERE post_id = '{$post_id_by_comment_id[post_id]}'")->fetch_assoc();

                            ## get attachments fro this post
                            $post_attach = $this->connection->query("SELECT attachment_url, attachment_type FROM webhook_atachments WHERE comment_id = '{$post_id_by_comment_id[post_id]}'");

                            while ($post_attach_data = $post_attach->fetch_assoc()) {
                                $attach_json = array();
                                $attach_json['attachment_url'] = rawurldecode($post_attach_data['attachment_url']);
                                $attach_json['attachment_type'] = $post_attach_data['attachment_type'];

                                $attach_data_json[] = $attach_json;
                            }

                            $post_data_json['message'] = $post_data['message'];
                            $post_data_json['attch'] = $attach_data_json;

                            ## return all ticket list
                            $this->throwMessage('200', ["result" => $json_data, "post_data" => $post_data_json]);
                        } else {
                            ## fetch data for message
                            $dataResult = $this->connection->query("SELECT webhook_messages.sender_name, webhook_messages.message, webhook_messages.fb_messages_time, webhook_messages.sender_id, webhook_messages.sender_type, webhook_atachments.attachment_url, webhook_atachments.attachment_type, webhook_atachments.attachment_src FROM webhook_messages LEFT JOIN webhook_atachments ON webhook_messages.unique_id = webhook_atachments.unique_id WHERE webhook_messages.sender_id = '{$request->comment_id}' AND webhook_messages.authorized_by = '{$request->authorized_by}' ORDER BY webhook_messages.id ASC");

                            while ($row_comments = mysqli_fetch_assoc($dataResult)) {
                                $json['user_name'] = $row_comments['sender_name'];
                                $json['message'] = $row_comments['message'];
                                $json['comment_created_time'] = ["date" => date("d M, Y",strtotime($row_comments['fb_messages_time'])), "time" => date("h:i A",strtotime($row_comments['fb_messages_time']))];
                                $json['user_id'] = $row_comments['sender_id'];
                                $json['sender_type'] = $row_comments['sender_type'];
                                $json['attachment_type'] = $row_comments['attachment_type'];
                                $json['attachment_url'] = $row_comments['attachment_src'] != '' ? $this->baseUrl . 'img/download/' . $row_comments['attachment_src'] : '';

                                $json_data[] = $json;
                            }

                            ## update seen status
                            $this->connection->query("UPDATE webhook_messages SET seen_status = 1 WHERE sender_id = '{$request->comment_id}' AND authorized_by = '{$request->authorized_by}'");

			                ## return all ticket list
                            $this->throwMessage('200', ["result" => $json_data]);
                        }
                    } else {
                        ## return error message
                        $this->throwMessage('401', 'All Field Required!!');
                    }
                }
            }
        }
    }


    /**
     * ticketReply
     * @return list
     */
    public function ticketReply($request){
        ## validate request method
        $validatePost = $this->validatePostRequest($_SERVER['REQUEST_METHOD']);
        if ($validatePost) {
            ## validate content-type
            $validateContentType = $this->validateContentType($_SERVER["CONTENT_TYPE"]);
            if ($validateContentType) {
                ## validate token
                $validateToken = $this->validateToken(apache_request_headers());
                if ($validateToken['status']) {
                    ## check empty for request
                    if (!empty($request->authorized_by) && !empty($request->replay_id) && !empty($request->replay_data_type) && !empty($request->ticket_status) && !empty($request->username)) {
                        ## define variable
                        $is_validated = false;
                        $fb_messages_time = date('Y-m-d H:i:s');

                        ## get previous ticket data
		                $messageData = $this->connection->query("SELECT unique_id, ticket_first_reply_time FROM webhook_ticket WHERE comment_id = '{$request->replay_id}' AND authorized_by = '{$request->authorized_by}'")->fetch_assoc();

                        ## generate unique id
		                $unique_id = $this->unique_id($request->replay_id.$fb_messages_time);

                        ## check empty for closed status
                        if ($request->ticket_status == 'Closed' && !empty($request->disposition_type) && !empty($request->disposition_cat) && !empty($request->disposition_sub_cat) && !empty($request->label_id)) {
                            $is_validated = true; 
                        } else if(($request->ticket_status == 'Progress') && !empty($request->message_data != '')){
                            $is_validated = true; 
                        } else {
                            $is_validated = false;

                            ## return error message
                            $this->throwMessage('401', 'Please, fill all fields perfectly!!!');
                        }

                        ## check validation
                        if ($is_validated) {
                            ## for message reply
                            if($request->replay_data_type == 'message') {
                                ## check message empty
                                if (!empty($request->message_data)) {
                                    ## request data for facebook
                                    $data = array(
                                        "messaging_type" => "MESSAGE_TAG",
                                        "tag" => "ACCOUNT_UPDATE",
                                        "recipient" => array("id" => $request->replay_id),
                                        "message" => array("text" => $request->message_data),
                                    );

                                    ## request to facebook for reply
                                    $response = $this->curl_post($data, $request->authorized_by, $request->replay_page_id, 'https://graph.facebook.com/v9.0/me/messages?access_token=');
                                    

                                    ## sender mid after submit to facebook
					                $sender_mid = $response['msg']->message_id;

                                    ## remove (') special character from message
					                $message_data = str_replace("'", "\'", $request->message_data);

                                    ## insert messages table
                                    $this->connection->query("INSERT INTO webhook_messages (sender_id, mid, message, page_id, unique_id, authorized_by, service_status, sender_name, fb_messages_time, sender_type, parent_ref) VALUES ('{$request->replay_id}', '{$sender_mid}', '{$message_data}', '{$request->replay_page_id}', '{$unique_id}', '{$request->authorized_by}', 'Closed', '{$request->replay_name}', '{$fb_messages_time}', 'owner', '{$messageData[unique_id]}')");
                                }

                                ## update real time agent status
                                $this->updateRealTimeStatus($request->authorized_by, $request->username);

                                ## update service status
                                $this->updateServiceStatus($request->authorized_by, $messageData[unique_id], 'message');

                                ## update ticket status and data move
                                $this->updateTicketStatus($request->authorized_by, $messageData[unique_id], $request->ticket_status, $request->disposition_type, $request->disposition_cat, $request->disposition_sub_cat, $messageData[ticket_first_reply_time], $request->label_id, $fb_messages_time);

                                ## return error message
                                $this->throwMessage('200', 'Send Successfully!!');
                            } else {
                                ## get previous comments data
				                $commentData = $this->connection->query("SELECT * FROM webhook_comments WHERE comment_id = '{$request->replay_id}' AND authorized_by = '{$request->authorized_by}'")->fetch_assoc();

                                ## check message empty
                                if (!empty($request->message_data)) {

                                    ## request data for facebook
                                    $data = array(
                                        "message" => $request->message_data,
                                    );

                                    ## request to facebook for reply
                                    $response = $this->curl_post($data, $request->authorized_by, $request->replay_page_id, 'https://graph.facebook.com/' . $request->replay_id . '/comments?access_token=');

                                    ## replay comment id
					                $comment_mid = $response['msg']->id;

                                    ## remove (') special character from message
					                $message_data = str_replace("'", "\'", $request->message_data);
                                   
                                    ## insert comments table
                                    $this->connection->query("INSERT INTO `webhook_comments` (`comment_id`, `parent_id`, `post_id`, `user_id`, `user_name`, `status_type`, `is_published`, `post_updated_time`, `permalink_url`, `promotion_status`, `message`, `comment_created_time`, `unique_id`, `authorized_by`, service_status, parent_ref, sender_type, page_id) VALUES ('{$comment_mid}', '{$request->replay_id}', '{$commentData[post_id]}', '{$commentData[user_id]}', '{$request->replay_name}', '{$commentData[status_type]}', '{$commentData[is_published]}', '{$commentData[post_updated_time]}', '{$commentData[permalink_url]}', '{$commentData[promotion_status]}', '{$message_data}', '{$fb_messages_time}', '{$unique_id}', '{$request->authorized_by}', 'Closed', '{$commentData[parent_ref]}', 'owner', '{$request->replay_page_id}')");
                                    
                                }

                                ## update real time agent status
				                $this->updateRealTimeStatus($request->authorized_by, $request->username);

                                ## update service status
                                $this->updateServiceStatus($request->authorized_by, $commentData[unique_id], 'comment');

                                ## update ticket status and data move
                                $this->updateTicketStatus($request->authorized_by, $commentData[unique_id], $request->ticket_status, $request->disposition_type, $request->disposition_cat, $request->disposition_sub_cat, $messageData[ticket_first_reply_time], $request->label_id, $fb_messages_time);

                                ## return error message
                                $this->throwMessage('200', 'Send Successfully!!');
                            }
                        }
                    } else {
                        ## return error message
                        $this->throwMessage('401', 'All Field Required!!');
                    }
                }
            }
        }
    }

    /**
     * curl_post
     *
     * @param array $data
     * @param $replay_auth_id
     * @param $replay_page_id
     * @param $url
     * @return void
     */
    public function curl_post($data = array(), $replay_auth_id, $replay_page_id, $url){
		$accessDataResult = $this->connection->query("SELECT access_token FROM page WHERE authorized_by = '{$replay_auth_id}' AND page_id = '{$replay_page_id}'")->fetch_assoc();
        
		if ($accessDataResult['access_token'] != '') {
			$ch = curl_init($url.$accessDataResult['access_token']);
			curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch,CURLOPT_HTTPHEADER, array(
					'Content-Type:application/json'
				)
			);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			$resultDecode = json_decode($result);
            
			if (isset($resultDecode->recipient_id) && isset($resultDecode->message_id)) {
				return array("status" => "success", "msg" => $resultDecode);
			} else if(isset($resultDecode->id)){
				return array("status" => "success", "msg" => $resultDecode);
			}
		}
	}

    /**
     * unique_id
     *
     * @param $id
     * @return void
     */
    public function unique_id($id){
		return hash('ripemd160', $id);
	}

    /**
     * updateRealTimeStatus
     *
     * @param $auth_id
     * @param $user
     * @return void
     */
    public function updateRealTimeStatus($auth_id, $user){
		$this->connection->query("UPDATE realtime_agent SET is_free = 'yes' WHERE authorized_by = '{$auth_id}' AND user = '{$user}'");
	}

    /**
     * updateServiceStatus
     *
     * @param $auth_id
     * @param $parent_ref
     * @param $replay_data_type
     * @return void
     */
	public function updateServiceStatus($auth_id, $parent_ref, $replay_data_type){
		if ($replay_data_type == 'message') {
			$this->connection->query("UPDATE webhook_messages SET service_status = 'Closed' WHERE authorized_by = '{$auth_id}' AND parent_ref = '{$parent_ref}' AND service_status = 'Progress'");
		} else {
			$this->connection->query("UPDATE webhook_comments SET service_status = 'Closed' WHERE authorized_by = '{$auth_id}' AND parent_ref = '{$parent_ref}' AND service_status = 'Progress'");
		}
	}

    /**
     * updateTicketStatus
     *
     * @param $auth_id
     * @param $parent_ref
     * @param $ticket_status
     * @param $disposition_type
     * @param $disposition_cat
     * @param $disposition_sub_cat
     * @param $ticket_first_reply_time
     * @param $label_id
     * @param $fb_messages_time
     * @return void
     */
    public function updateTicketStatus($auth_id, $parent_ref, $ticket_status, $disposition_type, $disposition_cat, 		$disposition_sub_cat, $ticket_first_reply_time, $label_id, $fb_messages_time){
        ## calculate first replay time
		$reply_time = $ticket_first_reply_time == '' ? $fb_messages_time : $ticket_first_reply_time;

		if ($ticket_status == 'Closed') {
			## get message previous data
			$prevData = $this->connection->query("SELECT * FROM webhook_ticket WHERE unique_id = '{$parent_ref}' AND authorized_by = '{$auth_id}'")->fetch_assoc();

			## ticket close time
			$ticket_close_time = $fb_messages_time;

			## calculate time
			$queue_time = (strtotime($prevData['create_at']) - strtotime($prevData['fb_created_time']));
			$first_response_time = (strtotime($reply_time) - strtotime($prevData['create_at']));
			$handling_time = (strtotime($ticket_close_time) - strtotime($prevData['create_at']));

			## data move to log table
			$this->connection->query("INSERT INTO webhook_ticket_log (authorized_by, page_id, assign_user, status, is_parent, data_type, comment_id, unique_id, fb_created_time, ticket_first_reply_time, disposition_type, disposition_cat, disposition_sub_cat, label_id, assigned_time, queue_time, first_response_time, ticket_close_time, handling_time) VALUES ('{$prevData[authorized_by]}', '{$prevData[page_id]}', '{$prevData[assign_user]}', '{$ticket_status}', '{$prevData[is_parent]}', '{$prevData[data_type]}', '{$prevData[comment_id]}', '{$prevData[unique_id]}', '{$prevData[fb_created_time]}', '{$reply_time}', '{$disposition_type}', '{$disposition_cat}', '{$disposition_sub_cat}', '{$label_id}', '{$prevData[create_at]}', '{$queue_time}', '{$first_response_time}', '{$ticket_close_time}', '{$handling_time}')");

			## delete ticket after close and move data
			$this->connection->query("DELETE FROM webhook_ticket WHERE unique_id = '{$parent_ref}' AND authorized_by = '{$auth_id}'");
            
		} else {
			## update ticket table status
			$this->connection->query("UPDATE webhook_ticket SET status = '{$ticket_status}', ticket_first_reply_time = '{$reply_time}' WHERE authorized_by = '{$auth_id}' AND unique_id = '{$parent_ref}'");
		}
	}
}
?>