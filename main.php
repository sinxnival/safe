<?php
require_once "../../lib/include.php";
require_once "../common/safe_ini.php";

$mode = $_POST["mode"];

if($mode == "INIT") {

    $jno = $_POST["jno"];

    //JOB 정보
    $jobInfo = array();
    $SQL = "WITH T_PE AS (
        SELECT M.JNO, LISTAGG(U.USER_NAME, ',') WITHIN GROUP (ORDER BY M.UNO) AS PES
        FROM JOB_MEMBER_LIST M  
         LEFT OUTER JOIN S_SYS_USER_SET U ON M.UNO = U.UNO
        WHERE M.JNO = :jno AND M.COMP_TYPE = 'H' AND M.CHARGE = '13' 
        GROUP BY M.JNO
        )
        SELECT J.JNO, J.JOB_NO, J.JOB_NAME, TO_CHAR(J.JOB_SD, 'YYYY-MM-DD') AS JOB_SD, TO_CHAR(J.JOB_ED, 'YYYY-MM-DD') AS JOB_ED, J.JOB_PM, U.USER_NAME AS JOB_PM_NAME, J.JOB_PE, P.PES, 
         J.COMP_CODE, C.COMP_NAME, CASE WHEN J.ORDER_COMP_CODE IS NULL THEN J.ORDER_COMP_NAME ELSE OC.COMP_NAME END AS ORDER_COMP_NAME
        FROM JOB_INFO J 
         JOIN S_SYS_USER_SET U ON J.JOB_PM = U.UNO
         LEFT OUTER JOIN COMPANY_INFO C ON J.COMP_CODE = C.COMP_NO 
         LEFT OUTER JOIN COMPANY_INFO OC ON J.ORDER_COMP_CODE = OC.COMP_NO 
         LEFT OUTER JOIN T_PE P ON J.JNO = P.JNO 
        WHERE J.JNO = :jno ";
    $params = array(
        ":jno" => $jno
    );
    $timesheetDB->query($SQL, $params);
    $timesheetDB->next_record();
    $row = $timesheetDB->Record;

    $jobInfo = array(
        "jno" => $row["jno"],
        "jobNo" => $row["job_no"],
        "jobName" => $row["job_name"],
        "jobSd" => $row["job_sd"],
        "jobEd" => $row["job_ed"],
        "jobPm" => $row["job_pm"],
        "jobPmName" => $row["job_pm_name"],
        "jobPe" => $row["job_pe"],
        "jobPeName" => $row["pes"],
        "compName" => $row["comp_name"],
        "orderCompName" => $row["order_comp_name"]
    );

    //공지사항
    $noticeList = array();
    $SQL = "SELECT ROWNUM, BOARD_NO, TITLE, USER_NAME, REG_DATE 
            FROM (
            SELECT I.BOARD_NO, I.TITLE, I.REG_USER, U.USER_NAME, TO_CHAR(I.REG_DATE, 'YYYY-MM-DD') AS REG_DATE 
            FROM BOARD_INFO I 
            JOIN S_SYS_USER_SET U ON I.REG_USER = U.UNO 
            WHERE I.JNO = :jno 
            ORDER BY I.BOARD_NO DESC 
            ) 
            WHERE ROWNUM <= 3 ";
    $params = array(
        ":jno" => $jno
    );
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        $noticeList[] = array(
            "boardNo" => $row["board_no"],
            "title" => $row["title"],
            "userName" => $row["user_name"],
            "regDate" => $row["reg_date"]
        );
    }

    //알림
    $alarmList = array();
    //작성 요청
    $SQL = "SELECT I.SEQ, I.ASSESSMENT_ID, I.ASSESSMENT_TYPE, C.CD_NM AS ASSESSMENT_TYPE_NAME, T.FUNC_NO, F.FUNC_NAME, 
                TO_CHAR(I.START_DATE, 'YYYY-MM-DD') AS START_DATE, TO_CHAR(I.END_DATE, 'YYYY-MM-DD') AS END_DATE, TO_CHAR(I.DEADLINE, 'YYYY-MM-DD') AS DEADLINE 
            FROM RISK_APPROVAL_TARGET T
                JOIN RISK_ASSESSMENT_INFO I ON T.ASSESSMENT_ID = I.ASSESSMENT_ID
                JOIN RISK_CODE_SET C ON C.MAJOR_CD = 'ASSESSMENT_TYPE' AND I.ASSESSMENT_TYPE = C.MINOR_CD
                LEFT OUTER JOIN COMMON.COMM_FUNC_QHSE F ON T.FUNC_NO = F.FUNC_NO
            WHERE I.JNO = :jno
            AND TO_NUMBER(T.APPROVAL_STATUS) < " . APP_STATUS_SBM . " ";
    if ($_SESSION["risk"]["user_type"] == "HEAD") {
        $SQL .= " AND T.UNO = :uno ";
    }
    else if ($_SESSION["risk"]["user_type"] == "SUB") {
        $SQL .= " AND T.CNO = :cno ";
    }
    $SQL .= "ORDER BY I.ASSESSMENT_ID ";
    $params = array(
        ":jno" => $jno,
        ":uno" => $user->uno,
        ":cno" => $_SESSION["risk"]["cno"]
    );$test = $SQL;
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        $msg  = "{$row["seq"]}차 {$row["assessment_type_name"]}({$row["start_date"]}~{$row["end_date"]})";
        if (!empty($row["func_no"])) {
            $msg .= "-{$row["func_name"]}";
        }
        $msg .= "을(를)";
        if (!empty($row["deadline"])) {
            $msg .= " {$row["deadline"]}까지";
        }
        $msg .= " 작성 바랍니다.";
        $alarmList[] = array(
            "alarmType" => "ASMT",
            "assessmentId" => $row["assessment_id"],
            "assessmentType" => $row["assessment_type"],
            "funcNo" => $row["func_no"],
            "msg" => $msg
        );
    }

    //FEEDBACK 미작성
    if ($_SESSION["risk"]["user_type"] == "HEAD") {
        
    }
    else if ($_SESSION["risk"]["user_type"] == "SUB") {
        $today = new DateTime();
        $SQL = "WITH T_A AS (
            SELECT I.ASSESSMENT_ID, I.SEQ, I.ASSESSMENT_TYPE, I.START_DATE, I.END_DATE
            FROM RISK_ASSESSMENT_INFO I
             JOIN RISK_APPROVAL_TARGET T ON T.APPROVAL_TYPE = 'ASMT_MEETING' AND T.APPROVAL_STATUS = :approvalStatus AND I.ASSESSMENT_ID = T.ASSESSMENT_ID
            WHERE I.JNO = :jno
             AND I.ASSESSMENT_TYPE = 'ASMT_REP'
             AND :today BETWEEN I.START_DATE AND I.END_DATE
            )
            SELECT I.ASSESSMENT_ID, I.SEQ, I.ASSESSMENT_TYPE, C.CD_NM AS ASSESSMENT_TYPE_NAME, TO_CHAR(I.START_DATE, 'YYYY-MM-DD') AS START_DATE, TO_CHAR(I.END_DATE, 'YYYY-MM-DD') AS END_DATE, 
             D.RISK_FACTOR, D.SUBCON_USER_NAME, D.ASSESSMENT_ITEM_ID
            FROM T_A I 
             JOIN RISK_ASSESSMENT_DETAIL D ON I.ASSESSMENT_ID = D.ASSESSMENT_ID 
             JOIN RISK_CODE_SET C ON C.MAJOR_CD = 'ASSESSMENT_TYPE' AND I.ASSESSMENT_TYPE = C.MINOR_CD
             LEFT OUTER JOIN RISK_FEEDBACK F ON D.ASSESSMENT_ITEM_ID = F.ASSESSMENT_ITEM_ID AND D.CNO = F.CNO AND F.FEEDBACK_DATE = :today
            WHERE D.IS_CHECK = 'Y' 
             AND D.CNO = :cno 
             AND F.ASSESSMENT_ITEM_ID IS NULL ";
        $params = array(
            ":jno" => $jno,
            ":approvalStatus" => APP_STATUS_APP_MTG,
            ":cno" => $_SESSION["risk"]["cno"],
            ":today" => $today->format("Y-m-d")
        );
        $db->query($SQL, $params);
        while($db->next_record()) {
            $row = $db->Record;
    
            $msg  = "{$row["seq"]}차 {$row["assessment_type_name"]}({$row["start_date"]}~{$row["end_date"]})의 ";
            $msg .= "[{$row["risk_factor"]}] 피드백을";
            if (!empty($row["subcon_user_name"])) {
                $msg .= " {$row["subcon_user_name"]}님";
            }
            $msg .= " 작성 바랍니다.";
            $alarmList[] = array(
                "alarmType" => "FEEDBACK",
                "assessmentId" => $row["assessment_id"],
                "assessmentItemId" => $row["assessment_item_id"],
                "feedbackDate" => $today->format("Y-m-d"),
                "msg" => $msg
            );
        }
    }

    //수시평가현황
    $userType = $_SESSION["risk"]["user_type"];
    $SQL = "WITH T_FEEDBACK AS (	
                                    SELECT S.CNO, F.FUNC_NO, MAX(FB.FEEDBACK_DATE) AS FEEDBACK_DATE
                                    FROM JOB_SUBCON_INFO S
                                    RIGHT OUTER JOIN JOB_SUBCON_FUNC F ON S.JNO = F.JNO AND F.CNO = S.CNO
                                    LEFT OUTER JOIN RISK_ASSESSMENT_INFO I ON I.JNO = S.JNO
                                    LEFT OUTER JOIN RISK_ASSESSMENT_DETAIL D ON I.ASSESSMENT_ID = D.ASSESSMENT_ID AND F.FUNC_NO = D.FUNC_NO AND D.IS_CHECK = 'Y'
                                    LEFT OUTER JOIN RISK_FEEDBACK FB ON FB.ASSESSMENT_ID = I.ASSESSMENT_ID AND FB.CNO = S.CNO AND FB.ASSESSMENT_ITEM_ID = D.ASSESSMENT_ITEM_ID
                                    WHERE S.JNO = :jno
                                    AND I.SEQ = (SELECT MAX(SEQ) FROM RISK_ASSESSMENT_INFO WHERE JNO = :jno AND ASSESSMENT_TYPE = 'ASMT_REP')
                                    GROUP BY S.CNO, F.FUNC_NO
                                    ORDER BY CNO, FUNC_NO
            )
            SELECT ASSESS_SEQ, START_DATE, END_DATE, CNO, COMP_NAME, FUNC_NO, FUNC_NAME, FEEDBACK_DATE, SUBMIT_DATE, SUPERVISOR_MOD_DATE, SAFETY_MANAGER_MOD_DATE, SUPERINTENDENT_MOD_DATE, SORT_NO
            FROM (
                    SELECT I.SEQ AS ASSESS_SEQ, I.START_DATE, I.END_DATE, S.CNO, S.COMP_NAME, F.FUNC_NO, Q.FUNC_NAME, 
                            TO_CHAR(T.FEEDBACK_DATE, 'YYYY-MM-DD') AS FEEDBACK_DATE, TO_CHAR(A.SUBMIT_DATE, 'YYYY-MM-DD') AS SUBMIT_DATE, AI.AUTH, AI.SEQ, TO_CHAR(CAST(AI.MOD_DATE AS DATE), 'YYYY-MM-DD') AS MOD_DATE,
                            Q.SORT_NO
                    FROM RISK_ASSESSMENT_INFO I
                    RIGHT OUTER JOIN JOB_SUBCON_INFO S ON I.JNO = S.JNO
                    RIGHT OUTER JOIN JOB_SUBCON_FUNC F ON S.JNO = F.JNO AND S.CNO = F.CNO
                    INNER JOIN COMMON.COMM_FUNC_QHSE Q ON F.FUNC_NO = Q.FUNC_NO
                    INNER JOIN T_FEEDBACK T ON T.CNO = S.CNO AND T.FUNC_NO = F.FUNC_NO
                    INNER JOIN RISK_APPROVAL_TARGET A ON F.FUNC_NO = A.FUNC_NO AND S.CNO = A.CNO AND A.ASSESSMENT_ID = I.ASSESSMENT_ID
                    FULL OUTER JOIN APPROVAL_INFO AI ON A.APPROVAL_TARGET_ID = AI.APPROVAL_TARGET_ID AND AI.IS_SIGN = 'Y' AND AI.SIGN_KIND >= " . APP_STATUS_SBM . " ";
            $SQL .= "WHERE I.JNO = :jno
                    AND I.ASSESSMENT_TYPE = 'ASMT_REP'
                    AND I.SEQ = (SELECT MAX(I.SEQ) FROM RISK_ASSESSMENT_INFO I WHERE I.JNO = :jno AND I.ASSESSMENT_TYPE = 'ASMT_REP') ";
    if($userType == "SUB") {
        $cno = $_SESSION["risk"]["cno"];
        $SQL .= "AND S.CNO = {$cno}";
    }
    $SQL .= ")
            PIVOT (
            MAX(SEQ) AS SEQ , MAX(MOD_DATE) AS MOD_DATE FOR AUTH IN('SUPERVISOR' AS SUPERVISOR, 'SAFETY_MANAGER' AS SAFETY_MANAGER, 'SUPERINTENDENT' AS SUPERINTENDENT)
            )
            ORDER BY CNO, SORT_NO";
    $params = array(
        ":jno" => $jno
    );
    $db->query($SQL, $params);

    while($db->next_record()) {
        $row = $db->Record;

        $riskAssessmentState[] = array(
            "assess_seq" => $row["assess_seq"],
            "startDate" => $row["start_date"],
            "endDate" => $row["end_date"],
            "compName" => $row["comp_name"],
            "funcName" => $row["func_name"],
            "feedbackDate" => $row["feedback_date"],
            "submitDate" => $row["submit_date"],
            "supervisorModDate" => $row["supervisor_mod_date"],
            "safetyManagerModDate" => $row["safety_manager_mod_date"],
            "superintendentModDate" => $row["superintendent_mod_date"]
        );
    }

    //담당자 연락처
    $SQL = "SELECT M.JNO, M.AUTH, M.UNO, U.USER_NAME, U.CELL, U.DUTY_NAME, LISTAGG(Q.FUNC_NAME, ',') WITHIN GROUP(ORDER BY F.FUNC_NO) AS FUNC_NAME
            FROM JOB_MANAGER M
            FULL OUTER JOIN JOB_MANAGER_FUNC F ON M.JNO = F.JNO AND M.UNO = F.UNO
            INNER JOIN S_SYS_USER_SET U ON M.UNO = U.UNO
            LEFT OUTER JOIN COMMON.COMM_FUNC_QHSE Q ON F.FUNC_NO = Q.FUNC_NO
            WHERE M.JNO = :jno
            AND M.AUTH <> 'SUPERINTENDENT'
            GROUP BY M.JNO, M.AUTH, M.UNO, U.USER_NAME, U.CELL, U.DUTY_NAME";
    $db->query($SQL, $params);

    while($db->next_record()) {
        $row = $db->Record;

        $jobManagerInfo[] = array(
            "jno" =>  $row["jno"],
            "auth" => $row["auth"],
            "uno" => $row["uno"],
            "userName" => $row["user_name"],
            "cell" => $row["cell"],
            "dutyName" => $row["duty_name"],
            "funcName" => $row["func_name"]
        );
    }

    $result = array("test" => $test,
        "jobInfo" => $jobInfo,
        "noticeList" => $noticeList,
        "alarmList" => $alarmList,
        "riskAssessmentState" => $riskAssessmentState,
        "jobManagerInfo" => $jobManagerInfo
    );
    
    echo json_encode($result);
}
//날씨
else if($mode == "WEATHER") {

    $timeNow = strToTime(date("Y-m-d H:i:s"));
    $timeTarget = strToTime(date("Y-m-d 03:00:00"));

    if($timeNow > $timeTarget) {
        $baseDate = date("Ymd");
    } else {
        $baseDate = date("Ymd", strToTime(date("Y-m-d 02:00:00"), "-1 day"));
    }
    //인증키
    $serviceKey = "lBNZoyQOWXYmb0IxIe66ZjPHs3S08mTVJJ97FJv35QZvgbbeo4orlAgehXudOAcTdEiKaeSNf%2F3DAa%2Fulsm9WA%3D%3D";

    $url = "http://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getVilageFcst?serviceKey={$serviceKey}&numOfRows=1000&pageNo=1&base_date={$baseDate}&base_time=0200&nx=73&ny=67&dataType=JSON";
            
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

    $items = $responseResult->response->body->items->item;

    $forecast = array();
    for($i=0; $i < count($items); $i++) {
        //기온(최저/최고)
        if($items[$i]->category == "TMN" || $items[$i]->category == "TMX") {
            $forecast[$items[$i]->fcstDate][$items[$i]->category] = $items[$i]->fcstValue;
        }

        //하늘 상태
        if($items[$i]->category == "PTY" && $items[$i]->fcstTime == date("H00")) {
            if($items[$i]->fcstValue == "0") {
                if($items[$i-1]->fcstValue == "1") {
                    $forecast[$items[$i-1]->fcstDate]["SKY"] = '<i class="fa-solid fa-sun" style="font-size:x-large;" title="맑음"></i>';
                } else if($items[$i-1]->fcstValue == "3") {
                    $forecast[$items[$i-1]->fcstDate]["SKY"] = '<i class="fa-solid fa-cloud" style="font-size:x-large;" title="구름많음"></i>';
                } else if($items[$i-1]->fcstValue == "4") {
                    $forecast[$items[$i-1]->fcstDate]["SKY"] = '<i class="fa-solid fa-cloud-sun" style="font-size:x-large" title="흐림"></i>';
                }
            } else if(($items[$i]->fcstValue) == "1") {
                $forecast[$items[$i]->fcstDate]["SKY"] = '<i class="fa-solid fa-umbrella" style="font-size:x-large" title="비"></i>';
            } else if($items[$i]->fcstValue == "2") {
                $forecast[$items[$i]->fcstDate]["SKY"] = '<i class="fa-solid fa-umbrella" style="font-size:x-large" title="비/눈"></i> / <i class="fa-solid fa-snowflake" style="font-size:x-large" title="비/눈"></i>';
            } else if($items[$i]->fcstValue == "3") {
                $forecast[$items[$i]->fcstDate]["SKY"] = '<i class="fa-solid fa-snowflake" style="font-size:x-large" title="눈"></i>';
            } else if($items[$i]->fcstValue == "4") {
                $forecast[$items[$i]->fcstDate]["SKY"] = '<i class="fa-solid fa-cloud-showers-heavy" style="font-size:x-large" title="소나기"></i>';
            }
        }

        //풍속
        if($items[$i]->category == "WSD" && $items[$i]->fcstTime == date("H00")) {
            $forecast[$items[$i]->fcstDate][$items[$i]->category] = $items[$i]->fcstValue;
        }
    }

    $result = array(
        "forecast" => $forecast
    );
    
    echo json_encode($result);
}
?>
