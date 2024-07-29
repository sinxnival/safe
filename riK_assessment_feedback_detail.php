<?php
require_once "../../lib/include.php";
require_once "../common/safe_ini.php";

$mode = $_POST["mode"];

if($mode == "INIT") {
    $assessmentId = $_POST["assessmentId"];
    $assessmentItemId = $_POST["assessmentItemId"];
    $feedbackDate = $_POST["feedbackDate"];
    $jno = $_POST["jno"];

    //위험성 평가 차수 정보
    $SQL = "SELECT I.SEQ, TO_CHAR(I.START_DATE,'YYYY-MM-DD') AS START_DATE, TO_CHAR(I.END_DATE,'YYYY-MM-DD') AS END_DATE, D.CNO, D.RISK_FACTOR,
                    TO_CHAR(TO_DATE(:FEEDBACK_DATE) , 'YYYY-MM-DD') AS FEEDBACK_DATE
            FROM RISK_ASSESSMENT_INFO I, RISK_ASSESSMENT_DETAIL D
            WHERE I.ASSESSMENT_ID = D.ASSESSMENT_ID
            AND I.ASSESSMENT_ID = :ASSESSMENT_ID
            AND D.ASSESSMENT_ITEM_ID = :ASSESSMENT_ITEM_ID";
    $params = array(
        ":FEEDBACK_DATE" => $feedbackDate,
        ":ASSESSMENT_ID" => $assessmentId,
        ":ASSESSMENT_ITEM_ID" => $assessmentItemId
    );
    $db->query($SQL, $params);
    $db->next_record();
    $row = $db->Record;

    $url = "http://wcfservice.htenc.co.kr/apipcs/getsubcomp?cno={$row["cno"]}";

    $curl = curl_init();

    curl_setopt_array($curl, array(
    //         CURLOPT_PORT => "80",
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET", 
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: text/plain; charset=utf-8"
        ),
    ));

    $response = curl_exec($curl);
    //     $err = curl_error($curl);
    curl_close($curl);

    $responseResult = json_decode($response);

    $compName = $responseResult->Value[0]->COMPNAME;

    $assessmentItemInfo = array(
        "seq" => $row["seq"],
        "startDate" => $row["start_date"],
        "endDate" => $row["end_date"],
        "cno" => $row["cno"],
        "compName" => $compName,
        "riskFactor" => $row["risk_factor"],
        "feedbackDate" => $row["feedback_date"]
    );

    //권한
    $authList = array();
    $SQL  = "SELECT MINOR_CD, CD_NM ";
    $SQL .= "FROM RISK_CODE_SET ";
    $SQL .= "WHERE MAJOR_CD = 'AUTH' ";
    $SQL .= " AND IS_USE = 'Y' ";
    $SQL .= "ORDER BY VAL5 ";
    $db->query($SQL);
    while($db->next_record()) {
        $row = $db->Record;

        $authList[] = array(
            "code" => strtolower($row["minor_cd"]),
            "name" => $row["cd_nm"]
        );
    }

    //위험성평가 안전보건관리대책 상세
    $assessmentList = getAssessmentList($assessmentId, $assessmentItemId, $jno);

    //점검상태
    $SQL = "SELECT MINOR_CD, CD_NM
            FROM RISK_CODE_SET
            WHERE MAJOR_CD = 'INSPECTION_STATE'
            AND IS_USE = 'Y'
            ORDER BY VAL5";
    $db->query($SQL);
    
    while($db->next_record()) {
        $row =  $db->Record;

        $inspectionList[] = array(
            "minorCd" => $row["minor_cd"],
            "cdNm" => $row["cd_nm"]
        );
    }

    //피드백 정보
    $SQL = "SELECT ASSESSMENT_ITEM_ID, TO_CHAR(FEEDBACK_DATE,'YY/FMMM/DD') AS FEEDBACK_DATE, INSPECTION_STATE, INSPECTION_NOTE, ASSESSMENT_ID
            FROM RISK_FEEDBACK
            WHERE ASSESSMENT_ITEM_ID = :ASSESSMENT_ITEM_ID
            AND FEEDBACK_DATE = TO_DATE(:FEEDBACK_DATE)";
    $params = array(
        ":ASSESSMENT_ITEM_ID" => $assessmentItemId,
        ":FEEDBACK_DATE" => $feedbackDate
    );
    $db->query($SQL, $params);

    while($db->next_record()) {
        $row = $db->Record;

        $feedbackInfo = array(
            "inspectionState" => $row["inspection_state"],
            "inspectionNote" => $row["inspection_note"]
        );
    }

    //이미지 불러오기
    $SQL = "SELECT FEEDBACK_FNO, FEEDBACK_DATE, FILE_NAME, FILE_LOCATION
            FROM RISK_FEEDBACK_ATCH
            WHERE ASSESSMENT_ITEM_ID = :ASSESSMENT_ITEM_ID
            AND FEEDBACK_DATE = :FEEDBACK_DATE";
    $db->query($SQL, $params);

    while($db->next_record()) {
        $row = $db->Record;

        $fileLocation = strstr($row["file_location"], "upload/");

        $feedbackImgList[] = array(
            "feedbackFno" => $row["feedback_fno"],
            "feedbackDate" => $row["feedback_date"],
            "fileName" => $row["file_name"],
            "fileLocation" => $fileLocation
        );
    }

    //해당 프로젝트 관리감독자, 안전관리자, 현장소장
    $SQL = "SELECT AUTH, UNO
            FROM JOB_MANAGER
            WHERE JNO = :JNO";
    $params = array(
        ":JNO" => $jno
    );
    $db->query($SQL, $params);

    while($db->next_record()) {
        $row = $db->Record;

        $jobManagerList[] = array(
            "auth" => $row["auth"],
            "uno" => $row["uno"]
        );
    }

    $result = array(
        "assessmentItemInfo" => $assessmentItemInfo,
        "assessmentList" => $assessmentList,
        "authList" => $authList,
        "inspectionList" => $inspectionList,
        "feedbackInfo" => $feedbackInfo,
        "feedbackImgList" => $feedbackImgList,
        "jobManagerList" => $jobManagerList
    );
    
    echo json_encode($result);
}
//저장
else if($mode == "SAVE") {
    $assessmentId = $_POST["assessmentId"];
    $assessmentItemId = $_POST["assessmentItemId"];
    $feedbackDate = $_POST["feedbackDate"];
    $cno = $_POST["cno"];
    $inspectionState = $_POST["inspectionState"];
    $inspectionNote = $_POST["inspectionNote"];
    $jno = $_POST["jno"];
    $proceed = true;

    $SQL = "MERGE INTO RISK_FEEDBACK F
            USING DUAL
            ON (F.ASSESSMENT_ITEM_ID = :ASSESSMENT_ITEM_ID AND F.FEEDBACK_DATE = TO_DATE(:FEEDBACK_DATE))
            WHEN MATCHED THEN
                UPDATE SET F.INSPECTION_STATE = :INSPECTION_STATE,
                            F.INSPECTION_NOTE = :INSPECTION_NOTE,
                            F.MOD_USER = :MOD_USER,
                            F.MOD_DATE = SYSTIMESTAMP
            WHEN NOT MATCHED THEN
                INSERT (F.ASSESSMENT_ITEM_ID, F.FEEDBACK_DATE, F.CNO, F.INSPECTION_STATE, F.INSPECTION_NOTE, F.ASSESSMENT_ID, F.REG_USER, F.REG_DATE)
                VALUES (:ASSESSMENT_ITEM_ID, :FEEDBACK_DATE, :CNO, :INSPECTION_STATE, :INSPECTION_NOTE, :ASSESSMENT_ID, :REG_USER, SYSTIMESTAMP)";
    $params = array(
        ":ASSESSMENT_ITEM_ID" => $assessmentItemId,
        ":FEEDBACK_DATE" => $feedbackDate,
        ":CNO" => $cno,
        ":INSPECTION_STATE" => $inspectionState,
        ":INSPECTION_NOTE" => $inspectionNote,
        ":ASSESSMENT_ID" => $assessmentId,
        ":REG_USER" => $user->uno,
        ":MOD_USER" => $user->uno
    );
    if($db->query($SQL, $params)) {
        //첨부파일 삭제
        $deleteAttachIdList = $_POST["deleteAttachIdList"];
        if($deleteAttachIdList) {
            foreach($deleteAttachIdList as $id) {
                //물리 삭제
                $SQL = "SELECT FILE_LOCATION 
                        FROM RISK_FEEDBACK_ATCH
                        WHERE FEEDBACK_FNO = :FEEDBACK_FNO";
                $params = array(
                    ":FEEDBACK_FNO" => $id
                );
                $db->query($SQL, $params);
                $db->next_record();
                $row = $db->Record;
                unlink($row["file_location"]);
    
                //데이터베이스 삭제
                $SQL = "DELETE FROM RISK_FEEDBACK_ATCH WHERE FEEDBACK_FNO = :FEEDBACK_FNO";
                $params = array(
                    ":FEEDBACK_FNO" => $id
                );
                $db->query($SQL, $params);
            }
        }
        //첨부파일 저장
        $imgFeedbackDate = str_replace('/','-', $feedbackDate);

        //존재파일
        $existAttach = $_POST["existAttach"];
        $existFileList = array();
        if (count($existAttach) > 0) {
            foreach($existAttach as $f) {
                $existFileList[]= strtolower($f);
            }
        }
        $fileList = array();
        for ($i=0; $i<count($_FILES['newAttachFile']['name']); $i++) {
            if (!empty($_FILES['newAttachFile']['name'][$i])) {
                $newFileName = "";
                $info = pathinfo($_FILES['newAttachFile']['name'][$i]);
                $oriFileName = $info['basename'];
                $ext = "." . $info['extension'];
                $fileName = $info['filename'];
                //지원하지 않는 특수문자 제거
                $fileName = iconv("UTF-8", "EUC-KR//TRANSLIT", $fileName);
                $fileName = iconv("EUC-KR", "UTF-8", $fileName);
                if(count($existFileList) > 0) {
                    $tempFileName = $fileName . $ext;
                    //파일 이름이 중복된다면 (n) 번을 붙여서 저장
                    if (in_array(strtolower($tempFileName), $existFileList)) {
                        $overCnt = array_count_values($existFileList);
                        $j = 1;
                        if($overCnt[$tempFileName] >= 2) {
                            $j = $overCnt[$tempFileName];
                        } 
                        do {
                            $tempFileName = $fileName . "(" . $j++ . ")" . $ext;
                        } while(in_array(strtolower($tempFileName), $existFileList));
                    }
                    $existFileList[] = strtolower($tempFileName);
                    $newFileName = $tempFileName;
                } else {
                    $newFileName = $fileName . $ext;
                }
                if(!file_exists("../upload/asmt/{$jno}/{$assessmentId}/feedback/{$assessmentItemId}/{$imgFeedbackDate}")) {
                    mkdir("../upload/asmt/{$jno}/{$assessmentId}/feedback/{$assessmentItemId}/{$imgFeedbackDate}", 0777, true);
                }
                $uploadDir = dirname(__DIR__) . "/upload/asmt/{$jno}/{$assessmentId}/feedback/{$assessmentItemId}/{$imgFeedbackDate}/" . $newFileName;
                $uploadFile = $_FILES['newAttachFile']['tmp_name'][$i];
                
                if (move_uploaded_file($uploadFile, $uploadDir)) {
                    $SQL = "INSERT INTO RISK_FEEDBACK_ATCH(FEEDBACK_FNO, ASSESSMENT_ITEM_ID, FEEDBACK_DATE, FILE_NAME, FILE_LOCATION)
                            VALUES(SEQ_FEEDBACK_FNO.NEXTVAL, :ASSESSMENT_ITEM_ID, :FEEDBACK_DATE, :FILE_NAME, :FILE_LOCATION)";
                    $params = array(
                        ":ASSESSMENT_ITEM_ID" => $assessmentItemId,
                        ":FEEDBACK_DATE" => $feedbackDate,
                        ":FILE_NAME" => $oriFileName,
                        ":FILE_LOCATION" => $uploadDir
                    );
                    if($db->query($SQL, $params)) {
                        $proceed = true;
                    };
                } else {
                    $proceed = false;
                }
            }
        }
    }
    if($proceed) {
        $msg = "저장되었습니다.";
    }

    $result = array(
        "proceed" => $proceed,
        "msg" => $msg
    ); 
    
    echo json_encode($result);
}
//삭제
else if($mode == "DELETE") {
    $jno = $_POST["jno"];
    $assessmentId = $_POST["assessmentId"];
    $assessmentItemId = $_POST["assessmentItemId"];
    $feedbackDate = $_POST["feedbackDate"];

    $imgFeedbackDate = str_replace('/','-', $feedbackDate);

    $SQL = "DELETE FROM RISK_FEEDBACK
            WHERE ASSESSMENT_ITEM_ID = :ASSESSMENT_ITEM_ID
            AND FEEDBACK_DATE = :FEEDBACK_DATE";
    $params = array(
        ":ASSESSMENT_ITEM_ID" => $assessmentItemId,
        ":FEEDBACK_DATE" => $feedbackDate
    );
    $db->query($SQL, $params);

    //물리삭제
    $deleteDir = dirname(__DIR__) . "/upload/asmt/{$jno}/{$assessmentId}/feedback/{$assessmentItemId}/{$imgFeedbackDate}/";

    if(file_exists($deleteDir)) {
        if(delTree($deleteDir)) {
            $SQL = "DELETE FROM RISK_FEEDBACK_ATCH
                    WHERE ASSESSMENT_ITEM_ID = :ASSESSMENT_ITEM_ID
                    AND FEEDBACK_DATE = :FEEDBACK_DATE";
            $db->query($SQL, $params);
        };
    }

    $result = array();

    echo json_encode($result);
}
//확인
else if($mode == "CHECK") {
    $assessmentItemId = $_POST["assessmentItemId"];
    $feedbackDate = $_POST["feedbackDate"];

    $SQL = "UPDATE RISK_FEEDBACK
            SET UNO = :UNO
            WHERE ASSESSMENT_ITEM_ID = :ASSESSMENT_ITEM_ID
            AND FEEDBACK_DATE = :FEEDBACK_DATE";
    $params = array(
        ":UNO" => $user->uno,
        ":ASSESSMENT_ITEM_ID" => $assessmentItemId,
        ":FEEDBACK_DATE" => $feedbackDate
    );
    $db->query($SQL, $params);

    $result = array();

    echo json_encode($result);
}

//안전보건관리대책 상세 가져오기
function getAssessmentList($assessmentId, $assessmentItemId, $jno) {
    global $db;
    global $dangerHazardLevel;
    global $funcColorList;

    $assessmentList = array();
    $SQL  = "WITH T_A AS (
        SELECT ASSESSMENT_ITEM_ID, SUBCONTRACTOR_USER_NAME, SUBCONTRACTOR_UNO, SUBCONTRACTOR_ACTION, SUPERVISOR_USER_NAME, SUPERVISOR_UNO, SUPERVISOR_ACTION, SAFETY_MANAGER_USER_NAME, 
                SAFETY_MANAGER_UNO, SAFETY_MANAGER_ACTION, SUPERINTENDENT_USER_NAME, SUPERINTENDENT_UNO, SUPERINTENDENT_ACTION, MEETING_USER_NAME, MEETING_ACTION
        FROM (
        SELECT A.ASSESSMENT_ITEM_ID, A.AUTH, U.USER_NAME, A.ACTION, U.UNO
        FROM RISK_ASSESSMENT_ACTION A
            LEFT OUTER JOIN S_SYS_USER_SET U ON A.UNO = U.UNO
        WHERE A.ASSESSMENT_ID = :assessmentId
        AND A.AUTH <> 'SUBCONTRACTOR'
        UNION
        SELECT A.ASSESSMENT_ITEM_ID, A.AUTH, S.COMP_NAME, A.ACTION, S.CNO
        FROM RISK_ASSESSMENT_ACTION A
            LEFT OUTER JOIN JOB_SUBCON_INFO S ON A.CNO = S.CNO AND S.JNO = :jno
        WHERE A.ASSESSMENT_ID = :assessmentId
        AND A.AUTH = 'SUBCONTRACTOR'
        )
        PIVOT (
         MAX(USER_NAME) AS USER_NAME, MAX(ACTION) AS ACTION, MAX(UNO) AS UNO FOR AUTH IN ('SUBCONTRACTOR' AS SUBCONTRACTOR, 'SUPERVISOR' AS SUPERVISOR, 'SAFETY_MANAGER' AS SAFETY_MANAGER, 'SUPERINTENDENT' AS SUPERINTENDENT, 'MEETING' AS MEETING)
        )
        ),
        T_W AS (
        SELECT WORK_TYPE_ID, SUBSTR(SYS_CONNECT_BY_PATH(WORK_TYPE_NAME, '>'), INSTR(SYS_CONNECT_BY_PATH(WORK_TYPE_NAME, '>'), '>', 1, 2) + 1) AS WORK_TYPE_PATH
        FROM RISK_WORK_TYPE
        WHERE CONNECT_BY_ISLEAF = 1
        START WITH PARENT_WORK_TYPE_ID = 0
        CONNECT BY PRIOR WORK_TYPE_ID = PARENT_WORK_TYPE_ID
        )
        SELECT D.FUNC_NO, F.FUNC_NAME, P.APPROVAL_STATUS, D.ASSESSMENT_ITEM_ID, D.WORK_TYPE_ID, W.WORK_TYPE_PATH, D.LOCATION, D.EQUIPMENT, D.RISK_TYPE, T.CD_NM AS RISK_TYPE_NAME, D.FREQUENCY, D.STRENGTH, D.IS_CHECK, D.SUBCON_USER_NAME, D.UNO,
                U.USER_NAME AS APPROVER_NAME, D.RISK_FACTOR, A.SUBCONTRACTOR_USER_NAME, A.SUBCONTRACTOR_ACTION, A.SUPERVISOR_USER_NAME, A.SUPERVISOR_ACTION, A.SAFETY_MANAGER_USER_NAME, A.SAFETY_MANAGER_ACTION, 
                A.SUPERINTENDENT_USER_NAME, A.SUPERINTENDENT_ACTION, A.MEETING_USER_NAME, A.MEETING_ACTION, D.ACTION_DEADLINE, A.SUBCONTRACTOR_UNO, A.SUPERVISOR_UNO, A.SAFETY_MANAGER_UNO, A.SUPERINTENDENT_UNO
        FROM RISK_ASSESSMENT_DETAIL D
            JOIN COMMON.COMM_FUNC_QHSE F ON D.FUNC_NO = F.FUNC_NO
            JOIN RISK_APPROVAL_TARGET P ON D.ASSESSMENT_ID = P.ASSESSMENT_ID AND D.CNO = P.CNO AND D.FUNC_NO = P.FUNC_NO
            JOIN T_W W ON D.WORK_TYPE_ID = W.WORK_TYPE_ID
            LEFT OUTER JOIN S_SYS_USER_SET U ON D.UNO = U.UNO
            LEFT OUTER JOIN T_A A ON D.ASSESSMENT_ITEM_ID = A.ASSESSMENT_ITEM_ID
            LEFT OUTER JOIN RISK_CODE_SET T ON T.MAJOR_CD = 'RISK_TYPE' AND D.RISK_TYPE = T.MINOR_CD
        WHERE D.ASSESSMENT_ID = :assessmentId
        AND D.ASSESSMENT_ITEM_ID = :assessmentItemId";
    $params = array(
        ":assessmentId" => $assessmentId,
        ":assessmentItemId" => $assessmentItemId,
        ":jno" => $jno
    );
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        $workTypePath = $row["work_type_path"];

        $rating = 0;
        if (!empty($row["frequency"]) && !empty($row["strength"])) {
            $rating = ($row["frequency"] * $row["strength"]);
        }
        $isDanger = false;
        if ($rating >= $dangerHazardLevel) {
            $isDanger = true;
        }
    
        $assessmentList = array(
            "assessmentItemId" => $row["assessment_item_id"], 
            "funcNo" => $row["func_no"], 
            //공종(현장) 배경색
            "funcColor" => $funcColorList[$row["func_no"]],
            "funcName" => $row["func_name"], 
            "workTypeId" => $row["work_type_id"], 
            "workTypeName" => $workTypePath, 
            "location" => nl2br($row["location"]), 
            "equipment" => nl2br($row["equipment"]), 
            "riskType" => $row["risk_type"], 
            "riskTypeName" => $row["risk_type_name"], 
            "riskFactor" => nl2br($row["risk_factor"]), 
            "frequency" => $row["frequency"], 
            "strength" => $row["strength"], 
            "rating" => $rating,
            "isDanger" => $isDanger,
            "isDanger" => $isDanger,
            "isCheck" => $row["is_check"], 
            "subcontractorUserName" => $row["subcontractor_user_name"],
            "subcontractorAction" => nl2br($row["subcontractor_action"]), 
            "supervisorUserName" => $row["supervisor_user_name"],
            "supervisorUno" => $row["supervisor_uno"],
            "supervisorAction" => nl2br($row["supervisor_action"]), 
            "safety_managerUserName" => $row["safety_manager_user_name"], 
            "safety_managerUno" => $row["safety_manager_uno"],
            "safety_managerAction" => nl2br($row["safety_manager_action"]), 
            "superintendentUserName" => $row["superintendent_user_name"], 
            "superintendentUno" => $row["superintendentUno"],
            "superintendentAction" => nl2br($row["superintendent_action"]),
            //회의내용
            "meetingAction" => nl2br($row["meeting_action"]), 
            //회의내용 - 작성자
            "meetingUserName" => $row["meeting_user_name"], 
            //조치자
            "subconUserName" => $row["subcon_user_name"], 
            //확인자
            "approverName" => $row["approver_name"], 
            "actionDeadline" => $row["action_deadline"]
        );
    }

    return $assessmentList;
}

//폴더 및 파일 삭제
function delTree($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}
?>
