<?php
require_once "../../lib/include.php";
require_once "../common/safe_ini.php";

$mode = $_POST["mode"];

if($mode == "INIT") {
    $jno = $_POST["jno"];

    $SQL = "SELECT I.ASSESSMENT_ID, I.SEQ, TO_CHAR(I.START_DATE, 'YYYY-MM-DD') AS START_DATE, TO_CHAR(I.END_DATE, 'YYYY-MM-DD') AS END_DATE
            FROM RISK_ASSESSMENT_INFO I ";
    $SQL .= "JOIN RISK_APPROVAL_TARGET T ON T.APPROVAL_TYPE = 'ASMT_MEETING' ";
    $SQL .= "AND T.APPROVAL_STATUS = :approvalStatus ";
    $SQL .= "AND I.ASSESSMENT_ID = T.ASSESSMENT_ID ";
    $SQL .= "WHERE I.JNO = :jno
             AND I.ASSESSMENT_TYPE = 'ASMT_REP' 
             ORDER BY SEQ";
    $params = array(
        ":jno" => $jno,
        ":approvalStatus" => APP_STATUS_APP_MTG
    );
    $db->query($SQL, $params);

    $today = new DateTime();
    $strToday = $today->format('Y-m-d');
    $rowCnt = $db->nf();
    $index = 1;
    $isToday = "N";
    while($db->next_record()) {
        $row = $db->Record;

        //차수 선택옵션
        $seqOption = $row["seq"] . "차 : " . $row["start_date"] . " ~ " . $row["end_date"];
        
        $isSelected = "N";
        if($row["start_date"] <= $strToday && $strToday <= $row["end_date"]) {
            $isSelected = "Y";
            $isToday = "Y";
        } else if($rowCnt == $index && $isToday == "N") {
            $isSelected = "Y";
        }

        $assessmentSeqList[] = array(
            "assessmentId" => $row["assessment_id"],
            "seq" => $row["seq"],
            "seqOption" => $seqOption,
            "isSelected" => $isSelected,
            "isToday" => $isToday
        );

        $index++;
    }

    $result = array(
        "assessmentSeqList" => $assessmentSeqList,
        "userType" => $_SESSION["risk"]["user_type"]
    );
    
    echo json_encode($result);
}
else if($mode == "LIST") {
    $assessmentId = $_POST["assessmentId"];
    $userType = $_POST["userType"];
    $cno = $_POST["cno"];

    //피드백 위험요인
    $SQL = "SELECT D.RISK_FACTOR, D.SUBCON_USER_NAME, U.USER_NAME, D.CNO, D.ASSESSMENT_ITEM_ID
            FROM RISK_ASSESSMENT_DETAIL D
            JOIN S_SYS_USER_SET U ON D.UNO = U.UNO(+)
            WHERE D.ASSESSMENT_ID = :assessmentId
            AND D.IS_CHECK = 'Y' ";
    if($userType == "SUB") {
        $SQL .= "AND D.CNO = :cno ";
    }
    $SQL .= "ORDER BY CNO, ASSESSMENT_ITEM_ID ";
    $params = array(
        ":assessmentId" => $assessmentId,
        ":cno" => $cno
    );
    $db->query($SQL, $params);

    while($db->next_record()) {
        $row = $db->Record;

        //협력업체 이름
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

        $feedbackFactorList[] = array(
            "riskFactor" => $row["risk_factor"],
            "subconUserName" => $row["subcon_user_name"],
            "userName" => $row["user_name"],
            "compName" => $compName,
            "assessmentItemId" => $row["assessment_item_id"]
        );
    }

    //점검상태
    $SQL = "WITH T_A AS (
                SELECT F.ASSESSMENT_ITEM_ID, F.FEEDBACK_DATE, LISTAGG(A.FEEDBACK_FNO, ',') WITHIN GROUP(ORDER BY A.FEEDBACK_FNO) AS HAS_FILE
                FROM RISK_FEEDBACK F
                LEFT OUTER JOIN RISK_FEEDBACK_ATCH A ON F.ASSESSMENT_ITEM_ID = A.ASSESSMENT_ITEM_ID AND F.FEEDBACK_DATE = A.FEEDBACK_DATE
                GROUP BY F.ASSESSMENT_ITEM_ID, F.FEEDBACK_DATE
            )
            SELECT F.ASSESSMENT_ITEM_ID, TO_CHAR(F.FEEDBACK_DATE,'YY/FMMM/DD') AS FEEDBACK_DATE, C.CD_NM, F.ASSESSMENT_ID, F.UNO, A.HAS_FILE
            FROM RISK_FEEDBACK F
            INNER JOIN T_A A ON F.ASSESSMENT_ITEM_ID = A.ASSESSMENT_ITEM_ID AND F.FEEDBACK_DATE = A.FEEDBACK_DATE
            INNER JOIN RISK_CODE_SET C ON C.MAJOR_CD = 'INSPECTION_STATE' AND F.INSPECTION_STATE = C.MINOR_CD AND C.IS_USE = 'Y'
            WHERE F.ASSESSMENT_ID = :assessmentId";
    $params = array(
        ":assessmentId" => $assessmentId
    );
    $db->query($SQL, $params);

    while($db->next_record()) {
        $row = $db->Record;

        $feedbackState[] = array(
            "assessmentItemId" => $row["assessment_item_id"],
            "feedbackDate" => $row["feedback_date"],
            "inspectionState" => $row["cd_nm"],
            "assessmentId" => $row["assessment_id"],
            "chkUno" => $row["uno"],
            "hasFile" => $row["has_file"]
        );
    }

    $result = array(
        "feedbackFactorList" => $feedbackFactorList,
        "feedbackState" => $feedbackState
    );
    
    echo json_encode($result);
}
?>
