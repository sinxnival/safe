<?php
require_once "../../lib/include.php";

$mode = $_POST["mode"];

if($mode == "INIT") {
    //협력업체 정보 가져오기
    $jno = $_POST["jno"];
    $cno = $_POST["cno"];

    $url = "http://wcfservice.htenc.co.kr/apipcs/getsubcomp?cno={$cno}";

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

    $subconDetail = array(
        "cno" => $responseResult->Value[0]->CNO,
        "regNo" => $responseResult->Value[0]->REGNO,
        "compName" => $responseResult->Value[0]->COMPNAME,
        "ceo" => $responseResult->Value[0]->CEO
    );

    //공종(현장) 종류 가져오기
    $SQL = "SELECT FUNC_NO, FUNC_NAME
            FROM COMMON.COMM_FUNC_QHSE
            WHERE IS_USE = 'Y'
            ORDER BY SORT_NO";

    $db->query($SQL);
    while($db->next_record()) {
        $row = $db->Record;

        $disciplineList[] = array(
            "funcNo" => $row["func_no"],
            "funcName" => $row["func_name"]
        );
    }
    
    //협력업체 정보
    $SQL = "SELECT S.JNO, S.CNO, S.ID, S.PW, S.CELLPHONE, S.EMAIL, U.USER_NAME, U.DUTY_NAME, S.UNO
            FROM JOB_SUBCON_INFO S, S_SYS_USER_SET U
            WHERE S.UNO = U.UNO(+)
            AND S.JNO = :JNO
            AND S.CNO = :CNO";
    $params = array(
        ":JNO" => $jno,
        ":CNO" => $cno
    );
    $db->query($SQL, $params);
    $db->next_record();
    $row = $db->Record;

    $subconInfo = array(
        "jno" => $row["jno"],
        "cno" => $row["cno"],
        "id" => $row["id"],
        "pw" => $row["pw"],
        "cellphone" => $row["cellphone"],
        "email" => $row["email"],
        "userName" =>  $row["user_name"],
        "dutyName" => $row["duty_name"],
        "uno" => $row["uno"]
    );
    
    //협력업체별 공종(현장)체크리스트
    $SQL = "SELECT F.JNO, F.CNO, C.FUNC_NO, C.FUNC_NAME
            FROM  JOB_SUBCON_FUNC F, COMMON.COMM_FUNC_QHSE C
            WHERE F.FUNC_NO = C.FUNC_NO
            AND F.JNO = :JNO
            AND F.CNO = :CNO";

    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        $disciplineDetail[$row["func_no"]] = array(
            "jno" => $row["jno"],
            "cno" => $row["cno"],
            "funcNo" => $row["func_no"],
            "funcName" => $row["func_name"]
        );
    }

    $result = array(
        "subconDetail" => $subconDetail,
        "disciplineList" => $disciplineList,
        "subconInfo" => $subconInfo,
        "disciplineDetail" => $disciplineDetail,
        "SQL" => $SQL
    );

    echo json_encode($result);
}
//저장
else if($mode == "SAVE") {
    $jno = $_POST["jno"];
    $cno = $_POST["cno"];
    $uno = $_POST["unoSV"];
    $cellphone = $_POST["subconPhone"];
    $email = $_POST["subconEmail"];
    $password = $_POST["regNo"];
    $compName = $_POST["compNm"];

    //수정/삽입 여부
    $SQL = "SELECT * 
            FROM JOB_SUBCON_INFO
            WHERE JNO = :JNO
            AND CNO = :CNO";
    $params = array(
        ":JNO" => $jno,
        ":CNO" => $cno
    );
    $db->query($SQL, $params);
    $cnt = $db->nf();

    //삽입을 경우
    if($cnt == 0) {
        $year = date("Y");
    
        $SQL = "SELECT MAX(SERIAL_NUM) AS SERIAL 
                FROM JOB_SUBCON_INFO 
                WHERE CREATE_YEAR = :CREATE_YEAR";

        $params = array(
            ":CREATE_YEAR" => $year
        );
        $db->query($SQL, $params);
        $db->next_record();
        $row = $db->Record;
    
        if($row["serial"] == null) {
            $serial = 1;
        } else {
            $serial = $row["serial"] + 1;
        }

        //아이디 제조
        $jnoId = sprintf('%05d', $jno);
        $yearId = substr($year, -2);
        $serialNum = sprintf('%04d', $serial);
        $subconId = $jnoId . $yearId . $serialNum;
    }

    //협력업체 삽입/수정
    $SQL = "MERGE INTO JOB_SUBCON_INFO S
            USING DUAL
            ON (S.JNO = :JNO AND S.CNO = :CNO)
            WHEN MATCHED THEN
                UPDATE SET S.UNO = :UNO,
                            S.CELLPHONE = :CELLPHONE,
                            S.EMAIL = :EMAIL
            WHEN NOT MATCHED THEN
                INSERT (S.JNO, S.CNO, S.ID, S.PW, S.CELLPHONE, S.EMAIL, S.UNO, S.IS_USE, S.CREATE_YEAR, S.SERIAL_NUM, S.COMP_NAME)
                VALUES (:JNO, :CNO, :ID, :PW, :CELLPHONE, :EMAIL, :UNO, 'Y', :CREATE_YEAR, :SERIAL_NUM, :COMP_NAME)";
    $params = array(
        ":JNO" => $jno,
        ":CNO" => $cno,
        ":UNO" => $uno,
        ":CELLPHONE" => $cellphone,
        ":EMAIL" => $email,
        ":ID" => $subconId,
        ":PW" => $password,
        ":CREATE_YEAR" => $year,
        ":SERIAL_NUM" => $serial,
        ":COMP_NAME" => $compName
    );
    $db->query($SQL, $params);

    //공종(현장) 삽입/수정
    $chkDisplineList = $_POST["chkDisciplineList"];

    $SQL = "DELETE FROM JOB_SUBCON_FUNC
            WHERE JNO = :JNO
            AND CNO = :CNO";
    $db->query($SQL, $params);

    if($chkDisplineList) {
        foreach($chkDisplineList as $funcCode) {
            $SQL = "INSERT INTO JOB_SUBCON_FUNC (JNO, CNO, FUNC_NO)
                    VALUES (:JNO, :CNO, :FUNC_NO)";
            $params = array(
                ":JNO" => $jno,
                ":CNO" => $cno,
                ":FUNC_NO" => $funcCode
            );
            $db->query($SQL, $params);
        }
    }

    $result = array();

    echo json_encode($result);
}

?>
