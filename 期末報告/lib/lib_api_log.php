<?php
/**
 * API 紀錄工具
 */

/**
 * 寫入 API 呼叫記錄
 */
function log_api($conn, $api_name, $action, $user_id, $status, $response_ms, $request_data = [], $response_data = []) {
    $req = $conn->real_escape_string(json_encode($request_data, JSON_UNESCAPED_UNICODE));
    $res = $conn->real_escape_string(json_encode($response_data, JSON_UNESCAPED_UNICODE));
    $uid = $user_id ? (int)$user_id : 'NULL';
    $api = $conn->real_escape_string($api_name);
    $act = $conn->real_escape_string($action);
    $sts = ($status === 'success') ? 'success' : 'fail';
    $ms = (int)$response_ms;

    $conn->query("INSERT INTO api_logs (api_name, action, user_id, status, response_ms, request_data, response_data)
        VALUES ('$api', '$act', $uid, '$sts', $ms, '$req', '$res')");
}