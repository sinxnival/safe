<?php
require_once "../../lib/include.php";

$mode = $_POST["mode"];

if($mode == "INIT") {
    //분류코드 가져오기
    $SQL = "SELECT MAJOR_CD, MINOR_CD, CD_NM, IS_USE
            FROM RISK_CODE_SET
            WHERE VAL1 = 'M'
            AND VAL4 = 'Y'
            ORDER BY MAJOR_CD";
    $db->query($SQL);
    while($db->next_record()) {
        $row = $db->Record;

        $majorCodeList[] = array(
            "majorCd" => $row["major_cd"],
            "minorCd" => $row["minor_cd"],
            "cdNm" => $row["cd_nm"],
            "isUse" => $row["is_use"]
        );
    }

    $result = array(
        "majorCodeList" => $majorCodeList
    );

    echo json_encode($result);
}
//분류코드에 따른 코드 가져오기
else if($mode == "LIST") {
    $majorCd = $_POST["majorCd"];

    $SQL = "SELECT MAJOR_CD, MINOR_CD, CD_NM, IS_USE, VAL5
            FROM RISK_CODE_SET
            WHERE MAJOR_CD = :majorCd
            AND VAL1 IS NULL
            AND VAL4 = 'Y'
            ORDER BY TO_NUMBER(VAL5), MINOR_CD";
    $params = array(
        ":majorCd" => $majorCd
    );
    $db->query($SQL, $params);

    while($db->next_record()) {
        $row = $db->Record;

        $minorCodeList[] = array(
            "majorCd" => $row["major_cd"],
            "minorCd" => $row["minor_cd"],
            "cdNm" => $row["cd_nm"],
            "isUse" => $row["is_use"],
            "val5" => $row["val5"]
        );
    }

    $result = array(
        "minorCodeList" => $minorCodeList
    );

    echo json_encode($result);
}
//분류코드 저장
else if($mode == "SAVE_MAJOR") {

    
    //분류코드
    $modifyMajorCd = $_POST["modifyMajorCd"];
    $modifyMajorCdNm = $_POST["modifyMajorCdNm"];
    $majorCdIsUse = $_POST["majorCdIsUse"];
    $addMajorCd = $_POST["addMajorCd"];
    $addMajorCdNm = $_POST["addMajorCdNm"];
    
    $overCnt = 0;
    $proceed = true;

    //코드 분류 변경
    if($modifyMajorCdNm) {
        foreach($modifyMajorCdNm as $key => $val) {
            $SQL = "SELECT *
                    FROM RISK_CODE_SET
                    WHERE MAJOR_CD = :MAJOR_CD
                    AND MINOR_CD = :MINOR_CD";
            $params = array(
                ":MAJOR_CD" => strtoupper($modifyMajorCd[$key]),
                ":MINOR_CD" => strtoupper($modifyMajorCd[$key]),
                ":CD_NM" => $val
            );
            $db->query($SQL, $params);
            $majorCnt = $db->nf();

            if($majorCnt > 1) {
                $overCnt++;
            }
        }

        if($overCnt == 0) {
            foreach($modifyMajorCdNm as $key => $val) {
                $SQL = "UPDATE RISK_CODE_SET
                        SET CD_NM = :CD_NM, MAJOR_CD = :MAJOR_CD, MINOR_CD = :MINOR_CD, IS_USE = :IS_USE
                        WHERE MAJOR_CD = '{$key}'
                        AND MINOR_CD = '{$key}'
                        AND VAL1 = 'M'";
                $params = array(
                    ":CD_NM" => $val,
                    ":MAJOR_CD" => $modifyMajorCd[$key],
                    ":MINOR_CD" => $modifyMajorCd[$key],
                    ":IS_USE" => $majorCdIsUse[$key]
                );
                $db->query($SQL, $params);
            }
        } else {
            $proceed = false;
            $msg = "입력하신 코드분류가 중복되었습니다. 확인하세요.";
        }
    }
    
    //새 코드 분류 삽입
    if($proceed) {
        if($addMajorCd) {
            foreach($addMajorCd as $key => $val) {
                $SQL = "SELECT *
                        FROM RISK_CODE_SET
                        WHERE MAJOR_CD = :MAJOR_CD
                        AND MINOR_CD = :MINOR_CD";
                $params = array(
                    ":MAJOR_CD" => strtoupper($val),
                    ":MINOR_CD" => strtoupper($val),
                    ":CD_NM" => $addMajorCdNm[$key]
                );
                $db->query($SQL, $params);
                $majorCnt = $db->nf();

                if($majorCnt > 0) {
                    $overCnt++;
                }
            }

            if($overCnt == 0) {
                foreach($addMajorCd as $key => $val) {
                    $SQL = "INSERT INTO RISK_CODE_SET(MAJOR_CD, MINOR_CD, CD_NM, VAL1, VAL4, IS_USE)
                            VALUES (:MAJOR_CD, :MINOR_CD, :CD_NM, 'M', 'Y', 'Y')";
                    $params = array(
                        ":MAJOR_CD" => strtoupper($val),
                        ":MINOR_CD" => strtoupper($val),
                        ":CD_NM" => $addMajorCdNm[$key]
                    );
                    $db->query($SQL, $params);
                }
            } else {
                $proceed = false;
                $msg = "입력하신 코드분류가 중복되었습니다. 확인하세요.";
            }
        }
    }    
    $result = array(
        "msg" => $msg
    );

    echo json_encode($result);
}
//요소코드 저장
else if($mode == "SAVE_MINOR") {

    //요소코드
    $majorCd = $_POST["majorCd"];
    $modifyMinorCd = $_POST["modifyMinorCd"];
    $modifyMinorCdNm = $_POST["modifyMinorCdNm"];
    $modifyMinorCdSort = $_POST["modifyMinorCdSort"];
    $addMinorCd = $_POST["addMinorCd"];
    $addMinorCdNm = $_POST["addMinorCdNm"];
    $addMinorCdSort = $_POST["addMinorCdSort"];
    $minorCdIsUse = $_POST["minorCdIsUse"];

    $overCnt = 0;
    $proceed = true;
    //요소코드 변경
    if($modifyMinorCdNm) {
        foreach($modifyMinorCdNm as $key => $val) {
            $SQL = "SELECT *
                    FROM RISK_CODE_SET
                    WHERE MAJOR_CD = :MAJOR_CD
                    AND MINOR_CD = :MINOR_CD";
            $params = array(
                ":MAJOR_CD" => $majorCd,
                ":MINOR_CD" => $key,
                ":CD_NM" => $modifyMinorCdNm[$key],
                ":VAL5" => $modifyMinorCdSort[$key]
            );
            $db->query($SQL, $params);
            $minorCnt = $db->nf();

            if($minorCnt > 1) {
                $overCnt++;
            }
        }

        if($overCnt == 0) {
            foreach($modifyMinorCdNm as $key => $val) {
                $SQL = "UPDATE RISK_CODE_SET
                        SET CD_NM = :CD_NM, MINOR_CD = :MINOR_CD, VAL5 = :VAL5, IS_USE = :IS_USE
                        WHERE MAJOR_CD = :MAJOR_CD
                        AND MINOR_CD = :KEY";
                $params = array(
                    ":CD_NM" => $val,
                    ":MINOR_CD" => $modifyMinorCd[$key],
                    ":VAL5" => $modifyMinorCdSort[$key],
                    ":IS_USE" => $minorCdIsUse[$key],
                    ":MAJOR_CD" => $majorCd,
                    ":KEY" => $key
                );
                $db->query($SQL, $params);
            }
        } else {
            $proceed = false;
            $msg = "입력하신 요소코드가 중복되었습니다. 확인하세요.";
        }

    }

    //새 요소코드 삽입
    if($proceed) {
        if($addMinorCd) {
            $overCnt = 0;
            foreach($addMinorCd as $key => $val) {
                $SQL = "SELECT *
                        FROM RISK_CODE_SET
                        WHERE MAJOR_CD = :MAJOR_CD
                        AND MINOR_CD = :MINOR_CD";
                $params = array(
                    ":MAJOR_CD" => $majorCd,
                    ":MINOR_CD" => strtoupper($val),
                    ":CD_NM" => $addMinorCdNm[$key],
                    ":VAL5" => $addMinorCdSort[$key]
                );
                $db->query($SQL, $params);
                $minorCnt = $db->nf();
    
                if($minorCnt > 0) {
                    $overCnt++;
                }
            }
            
            if($overCnt == 0) {
                foreach($addMinorCd as $key => $val) {
                    $SQL = "INSERT INTO RISK_CODE_SET(MAJOR_CD, MINOR_CD, CD_NM, IS_USE, VAL5, VAL4)
                            VALUES (:MAJOR_CD, :MINOR_CD, :CD_NM, 'Y', :VAL5, 'Y')";
                    $db->query($SQL, $params);
                    $params = array(
                        ":MAJOR_CD" => $majorCd,
                        ":MINOR_CD" => strtoupper($val),
                        ":CD_NM" => $addMinorCdNm[$key],
                        ":VAL5" => $addMinorCdSort[$key]
                    );
                }
            } else {
                $proceed = false;
        
                $msg = "입력하신 요소코드가 중복되었습니다. 확인하세요.";
            }
        }
    }
    $result = array(
        "msg" => $msg
    );

    echo json_encode($result);
}
//코드 삭제
else if($mode == "DEL_CODE") {
    $delMode = $_POST["delMode"];
    $delCd = $_POST["delCd"];
    $majorCd = $_POST["majorCd"];

    //분류코드
    if($delMode == "major") {
        $SQL = "UPDATE RISK_CODE_SET
                SET VAL4 = 'N'
                WHERE MAJOR_CD = :delCd";
        $params = array(
            ":delCd" => $delCd
        );
        $db->query($SQL, $params);
    }
    //요소코드 
    else {
        $SQL = "UPDATE RISK_CODE_SET
                SET VAL4 = 'N'
                WHERE MAJOR_CD = :majorCd
                AND MINOR_CD = :delCd";
        $params = array(
            ":majorCd" => $majorCd,
            ":delCd" => $delCd
        );
        $db->query($SQL, $params);
    }

    $result = array(
        "delMode" => $delMode
    );

    echo json_encode($result);
}
?>
