<?php
/*
 * 위험성평가 수시평가 - 결재
 */
require_once "../common/common.php";

// //세션 만료일 경우
// if (!isset($_SESSION["user"]["uno"])) {
//     echo json_encode(array("session_out" => true));
//     exit();
// }

$mode = $_POST["mode"];
$assessmentId = $_POST["assessmentId"];
$cno = $_POST["ddlSubcon"];
$funcNo = $_POST["ddlFuncNo"];
$jno = $_POST["jno"];

//일괄결재
if ("BATCH_SIGN" == $mode) {
    $auth = $_POST["auth"];
    $approverUno = $_POST["approverUno"];
    $signKind = "";
    $listKind = "";
    if ("SUPERVISOR" == $auth) {
        //담당자 승인
        $signKind = APP_STATUS_APP_L1;
        $listKind = 10;
    }
    else if ("SAFETY_MANAGER" == $auth) {
        //검토자 승인
        $signKind = APP_STATUS_APP_L2;
        $listKind = 20;
    }
    else if ("SUPERINTENDENT" == $auth) {
        //확인자 승인
        $signKind = APP_STATUS_APP_L3;
        $listKind = 30;
    }

    $proceed = false;
    $SQL  = "UPDATE APPROVAL_INFO ";
    $SQL .= "SET IS_SIGN = :isSign, ";
    $SQL .= " SIGN_KIND = :signKind, ";
    // $SQL .= " APPROVAL_NOTE = :approvalNote, ";
    $SQL .= " APPROVER_UNO = :approverUno, ";
    $SQL .= " MOD_USER = :modUser, ";
    $SQL .= " MOD_DATE = SYSTIMESTAMP ";
    $SQL .= "WHERE IS_SIGN = 'N' ";
    $SQL .= " AND AUTH = :auth ";
    $SQL .= " AND APPROVAL_TARGET_ID IN (SELECT APPROVAL_TARGET_ID FROM RISK_APPROVAL_TARGET WHERE ASSESSMENT_ID = :assessmentId ";
    if(!empty($listKind)) {
        $SQL .= " AND APPROVAL_STATUS = :listKind ";
    }
    if($auth == "SUPERVISOR" && $funcNo) {
        $SQL .= " AND FUNC_NO = :funcNo";
    }
    $SQL .= " ) ";
    $params = array(
        //결재 여부
        ":isSign" => "Y",
        //결재 구분 - 승인
        ":signKind" => $signKind,
        // //결재의견
        // ":approvalNote" => $returnReason,
        //결재자
        ":approverUno" => $approverUno,
        //수정자
        ":modUser" => $user->uno,
        //위험성평가 고유번호
        ":assessmentId" => $assessmentId,
        //권한
        ":auth" => $auth,
        //결재가능 문서
        ":listKind" => $listKind,
        //공종
        ":funcNo" => $funcNo
    );
    if ($db->query($SQL, $params)) {
        $proceed = true;
    }

    if (in_array($auth, array("SUPERVISOR", "SAFETY_MANAGER"))) {
        if ($proceed) {
            $proceed = false;
            $nextApp = "";
            if ("SUPERVISOR" == $auth) {
                $nextApp = "SAFETY_MANAGER";
            }
            else if ("SAFETY_MANAGER" == $auth) {
                $nextApp = "SUPERINTENDENT";
            }
            $SQL  = "INSERT INTO APPROVAL_INFO(APPROVAL_TARGET_ID, SEQ, AUTH, IS_SIGN, REG_USER, REG_DATE, MOD_USER, MOD_DATE) ";
            $SQL .= "SELECT APPROVAL_TARGET_ID, SEQ, :auth, :isSign, :regUser, SYSTIMESTAMP, :modUser, SYSTIMESTAMP ";
            $SQL .= "FROM ( ";
            $SQL .= " SELECT A.APPROVAL_TARGET_ID, (NVL(MAX(A.SEQ), 0) + 1) AS SEQ ";
            $SQL .= " FROM APPROVAL_INFO A ";
            $SQL .= "  JOIN RISK_APPROVAL_TARGET P ON A.APPROVAL_TARGET_ID = P.APPROVAL_TARGET_ID ";
            $SQL .= " WHERE P.ASSESSMENT_ID = :assessmentId ";
            if(!empty($listKind)) {
                $SQL .= " AND APPROVAL_STATUS = :listKind ";
            }
            $SQL .= " GROUP BY A.APPROVAL_TARGET_ID ";
            $SQL .= ") ";
            $params = array(
                ":auth" => $nextApp,
                ":isSign" => "N",
                ":regUser" => $user->uno,
                ":modUser" => $user->uno,
                //위험성평가 고유번호
                ":assessmentId" => $assessmentId,
                //결재가능 문서
                ":listKind" => $listKind
            );
            if ($db->query($SQL, $params)) {
                $proceed = true;
            }
        }
    }
    
    if ($proceed) {
        $proceed = false;
        $SQL  = "UPDATE RISK_APPROVAL_TARGET ";
        $SQL .= "SET APPROVAL_STATUS = :approvalStatus ";
        $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
        if(!empty($listKind)) {
            $SQL .= " AND APPROVAL_STATUS = :listKind ";
        }
        if($auth == "SUPERVISOR" && $funcNo) {
            $SQL .= " AND FUNC_NO = :funcNo";
        }
        $params = array(
            ":approvalStatus" => $signKind,
            //위험성평가 고유번호
            ":assessmentId" => $assessmentId,
            //결재가능 문서
            ":listKind" => $listKind,
            // 공종
            ":funcNo" => $funcNo
        );
        if ($db->query($SQL, $params)) {
            $proceed = true;
        }
    }

    //확인자
    // if ("SUPERVISOR" == $auth) {
    //     if ($proceed) {
    //         $proceed = false;
    //         $SQL  = "UPDATE RISK_ASSESSMENT_DETAIL ";
    //         $SQL .= "SET UNO = :approverUno ";
    //         $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
    //         $params = array(
    //             ":approverUno" => $approverUno,
    //             ":assessmentId" => $assessmentId
    //         );
    //         if ($db->query($SQL, $params)) {
    //             $proceed = true;
    //         }
    //     }
    // }

    if ($proceed) {
        $proceed = false;
        $SQL  = "UPDATE RISK_ASSESSMENT_INFO ";
        $SQL .= "SET {$auth}_APP_DATE = SYSDATE ";
        $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
        $params = array(
            ":assessmentId" => $assessmentId
        );
        if ($db->query($SQL, $params)) {
            $proceed = true;
        }
    }

    //회의 예정일 추가
    if($proceed) {
        if($auth == "SUPERINTENDENT") {
            $SQL = "SELECT T.ASSESSMENT_ID, COUNT(CASE WHEN TO_NUMBER(APPROVAL_STATUS) >= 10 THEN 1 END) AS CNT_SUBMIT, COUNT(CASE WHEN TO_NUMBER(APPROVAL_STATUS) >= 40 THEN 1 END) AS CNT_APPROVAL 
                    FROM RISK_APPROVAL_TARGET T 
                    JOIN RISK_ASSESSMENT_INFO I ON T.ASSESSMENT_ID = I.ASSESSMENT_ID AND I.JNO = :jno AND I.ASSESSMENT_TYPE = 'ASMT_REP' AND T.APPROVAL_TYPE = 'ASMT_REP' 
                    GROUP BY T.ASSESSMENT_ID 
                    HAVING T.ASSESSMENT_ID = :assessmentId";
            $params = array(
                ":jno" => $jno,
                ":assessmentId" => $assessmentId
            );
            $db->query($SQL, $params);
            $db->next_record();
            $row = $db->Record;

            if($row["cnt_submit"] == $row["cnt_approval"]) {
                $today = new DateTime();
                $SQL = "UPDATE RISK_ASSESSMENT_INFO
                        SET SCHEDULED_MEETING_DATE = TO_DATE(:scheduledMeetingDate, 'YYYY-MM-DD')
                        WHERE ASSESSMENT_ID = :assessmentId";
                $params = array(
                    ":assessmentId" => $assessmentId,
                    ":scheduledMeetingDate" => $today->format('Y-m-d')
                );
                if($db->query($SQL, $params)) {
                    $proceed = true;
                } else {
                    $proceed = false;
                }
            }
        }
    }

    if ($proceed) {
        $msg = "결재되었습니다.";
    }
    else {
        $msg = "결재 실패하였습니다.";
    }

    $result = array(
        "proceed" => $proceed,
        "msg" => $msg
    );

    echo json_encode($result);
}
//결재
else if ("SIGN" == $mode) {
    $approvalTargetId = $_POST["approvalTargetId"];
    $auth = $_POST["auth"];
    $approverUno = $_POST["approverUno"];
    $signKind = "";
    if ("SUPERVISOR" == $auth) {
        //담당자 승인
        $signKind = APP_STATUS_APP_L1;
    }
    else if ("SAFETY_MANAGER" == $auth) {
        //검토자 승인
        $signKind = APP_STATUS_APP_L2;
    }
    else if ("SUPERINTENDENT" == $auth) {
        //확인자 승인
        $signKind = APP_STATUS_APP_L3;
    }

    $proceed = false;
    $SQL  = "UPDATE APPROVAL_INFO ";
    $SQL .= "SET IS_SIGN = :isSign, ";
    $SQL .= " SIGN_KIND = :signKind, ";
    // $SQL .= " APPROVAL_NOTE = :approvalNote, ";
    $SQL .= " APPROVER_UNO = :approverUno, ";
    $SQL .= " MOD_USER = :modUser, ";
    $SQL .= " MOD_DATE = SYSTIMESTAMP ";
    $SQL .= "WHERE APPROVAL_TARGET_ID = :approvalTargetId ";
    $SQL .= " AND IS_SIGN = 'N' ";
    $SQL .= " AND AUTH = :auth ";
    $params = array(
        //결재 여부
        ":isSign" => "Y",
        //결재 구분 - 승인
        ":signKind" => $signKind,
        // //결재의견
        // ":approvalNote" => $returnReason,
        //결재자
        ":approverUno" => $approverUno,
        //수정자
        ":modUser" => $user->uno,
        //결재 대상 고유번호
        ":approvalTargetId" => $approvalTargetId,
        //권한
        ":auth" => $auth
    );
    if ($db->query($SQL, $params)) {
        $proceed = true;
    }

    if ($proceed) {
        $proceed = false;
        $SQL  = "UPDATE RISK_APPROVAL_TARGET ";
        $SQL .= "SET APPROVAL_STATUS = :approvalStatus ";
        $SQL .= "WHERE APPROVAL_TARGET_ID = :approvalTargetId ";
        $params = array(
            ":approvalStatus" => $signKind,
            //결재 대상 고유번호
            ":approvalTargetId" => $approvalTargetId
        );
        if ($db->query($SQL, $params)) {
            $proceed = true;
        }
    }

    if (in_array($auth, array("SUPERVISOR", "SAFETY_MANAGER"))) {
        if ($proceed) {
            $proceed = false;
            $nextApp = "";
            if ("SUPERVISOR" == $auth) {
                $nextApp = "SAFETY_MANAGER";
            }
            else if ("SAFETY_MANAGER" == $auth) {
                $nextApp = "SUPERINTENDENT";
            }
            $SQL  = "INSERT INTO APPROVAL_INFO(APPROVAL_TARGET_ID, SEQ, AUTH, IS_SIGN, REG_USER, REG_DATE, MOD_USER, MOD_DATE) ";
            $SQL .= "SELECT :approvalTargetId, (NVL(MAX(SEQ), 0) + 1), :auth, :isSign, :regUser, SYSTIMESTAMP, :modUser, SYSTIMESTAMP ";
            $SQL .= "FROM APPROVAL_INFO ";
            $SQL .= "WHERE APPROVAL_TARGET_ID = :approvalTargetId ";
            $params = array(
                ":approvalTargetId" => $approvalTargetId,
                ":auth" => $nextApp,
                ":isSign" => "N",
                ":regUser" => $user->uno,
                ":modUser" => $user->uno
            );
            if ($db->query($SQL, $params)) {
                $proceed = true;
            }
        }
    }

    //확인자
    // if ("SUPERVISOR" == $auth) {
    //     if ($proceed) {
    //         $proceed = false;
    //         $SQL  = "UPDATE RISK_ASSESSMENT_DETAIL ";
    //         $SQL .= "SET UNO = :approverUno ";
    //         $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
    //         $SQL .= " AND CNO = :cno ";
    //         $SQL .= " AND FUNC_NO = :funcNo ";
    //         $params = array(
    //             ":approverUno" => $approverUno,
    //             ":assessmentId" => $assessmentId,
    //             ":cno" => $cno,
    //             ":funcNo" => $funcNo
    //         );
    //         if ($db->query($SQL, $params)) {
    //             $proceed = true;
    //         }
    //     }
    // }

    if ($proceed) {
        $SQL  = "SELECT COUNT(APPROVAL_STATUS) AS CNT_ALL, COUNT(CASE WHEN TO_NUMBER(APPROVAL_STATUS) >= :approvalStatus THEN 1 END) AS CNT ";
        $SQL .= "FROM RISK_APPROVAL_TARGET ";
        $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
        $SQL .= " AND APPROVAL_TYPE = :approvalType ";
        $SQL .= "GROUP BY ASSESSMENT_ID ";
        $params = array(
            ":assessmentId" => $assessmentId,
            ":approvalStatus"=> $signKind,
            ":approvalType" => 'ASMT_REP'
        );
        $db->query($SQL, $params);
        $db->next_record();
        $row = $db->Record;
        if ($row["cnt_all"] == $row["cnt"]) {
            //승인일자
            $proceed = false;
            $SQL  = "UPDATE RISK_ASSESSMENT_INFO ";
            $SQL .= "SET {$auth}_APP_DATE = SYSDATE ";
            $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
            $params = array(
                ":assessmentId" => $assessmentId
            );
            if ($db->query($SQL, $params)) {
                $proceed = true;
            }
        }
    }

    //회의 예정일 추가
    if($proceed) {
        if($auth == "SUPERINTENDENT") {
            $SQL = "SELECT T.ASSESSMENT_ID, COUNT(CASE WHEN TO_NUMBER(APPROVAL_STATUS) >= 10 THEN 1 END) AS CNT_SUBMIT, COUNT(CASE WHEN TO_NUMBER(APPROVAL_STATUS) >= 40 THEN 1 END) AS CNT_APPROVAL 
                    FROM RISK_APPROVAL_TARGET T 
                    JOIN RISK_ASSESSMENT_INFO I ON T.ASSESSMENT_ID = I.ASSESSMENT_ID AND I.JNO = :jno AND I.ASSESSMENT_TYPE = 'ASMT_REP' AND T.APPROVAL_TYPE = 'ASMT_REP' 
                    GROUP BY T.ASSESSMENT_ID 
                    HAVING T.ASSESSMENT_ID = :assessmentId";
            $params = array(
                ":jno" => $jno,
                ":assessmentId" => $assessmentId
            );
            $db->query($SQL, $params);
            $db->next_record();
            $row = $db->Record;

            if($row["cnt_submit"] == $row["cnt_approval"]) {
                $today = new DateTime();
                $SQL = "UPDATE RISK_ASSESSMENT_INFO
                        SET SCHEDULED_MEETING_DATE = TO_DATE(:scheduledMeetingDate, 'YYYY-MM-DD')
                        WHERE ASSESSMENT_ID = :assessmentId";
                $params = array(
                    ":assessmentId" => $assessmentId,
                    ":scheduledMeetingDate" => $today->format('Y-m-d')
                );
                if($db->query($SQL, $params)) {
                    $proceed = true;
                } else {
                    $proceed = false;
                }
            }
        }
    }

    if ($proceed) {
        $msg = "결재되었습니다.";
    }
    else {
        $msg = "결재 실패하였습니다.";
    }

    $result = array(
        "proceed" => $proceed,
        "msg" => $msg
    );

    echo json_encode($result);
}
//반려
else if ("RETURN" == $mode) {
    $approvalTargetId = $_POST["approvalTargetId"];
    $returnReason = $_POST["returnReason"];
    $auth = $_POST["auth"];
    $approverUno = $_POST["approverUno"];
    $signKind = "";
    if ("SUPERVISOR" == $auth) {
        //담당자 반려
        $signKind = APP_STATUS_RET_L1;
    }
    else if ("SAFETY_MANAGER" == $auth) {
        //검토자 반려
        $signKind = APP_STATUS_RET_L2;
    }
    else if ("SUPERINTENDENT" == $auth) {
        //확인자 반려
        $signKind = APP_STATUS_RET_L3;
    }

    $proceed = false;
    $SQL  = "UPDATE APPROVAL_INFO ";
    $SQL .= "SET IS_SIGN = :isSign, ";
    $SQL .= " SIGN_KIND = :signKind, ";
    $SQL .= " APPROVAL_NOTE = :approvalNote, ";
    $SQL .= " APPROVER_UNO = :approverUno, ";
    $SQL .= " MOD_USER = :modUser, ";
    $SQL .= " MOD_DATE = SYSTIMESTAMP ";
    $SQL .= "WHERE APPROVAL_TARGET_ID = :approvalTargetId ";
    $SQL .= " AND IS_SIGN = 'N' ";
    $SQL .= " AND AUTH = :auth ";
    $params = array(
        //결재 여부
        ":isSign" => "Y",
        //결재 구분 - 반려
        ":signKind" => $signKind,
        //결재의견
        ":approvalNote" => $returnReason,
        //반려자
        ":approverUno" => $approverUno,
        //수정자
        ":modUser" => $user->uno,
        //결재 대상 고유번호
        ":approvalTargetId" => $approvalTargetId,
        //권한
        ":auth" => $auth
    );
    if ($db->query($SQL, $params)) {
        $proceed = true;
    }

    if ($proceed) {
        $proceed = false;
        $SQL  = "UPDATE RISK_APPROVAL_TARGET ";
        $SQL .= "SET APPROVAL_STATUS = :approvalStatus ";
        $SQL .= "WHERE APPROVAL_TARGET_ID = :approvalTargetId ";
        $params = array(
            ":approvalStatus" => $signKind,
            //결재 대상 고유번호
            ":approvalTargetId" => $approvalTargetId
        );
        if ($db->query($SQL, $params)) {
            $proceed = true;
        }
    }

    if ($proceed) {
        $msg = "반려되었습니다.";
    }
    else {
        $msg = "반려 실패하였습니다.";
    }

    $result = array(
        "proceed" => $proceed,
        "msg" => $msg
    );

    echo json_encode($result);
}
//위험요인 저장
else if ("SAVE_RISK_FACTOR" == $mode) {
    $riskFactorSubcon = $_POST["ddlRiskFactorSubcon"];
    $riskFactorFuncNo = $_POST["ddlRiskFactorFuncNo"];
    $selWorkTypeIdList = $_POST["selWorkTypeId"];
    $selRiskFactorIdList = $_POST["selRiskFactorId"];
    $selRiskFactorList = $_POST["selRiskFactor"];
    $ddlRiskFactorSV = $_POST["ddlRiskFactorSV"];

    for($i = 0; $i < count($selRiskFactorList); $i++) {
        $SQL  = "INSERT INTO RISK_ASSESSMENT_DETAIL (ASSESSMENT_ITEM_ID, ASSESSMENT_ID, FUNC_NO, WORK_TYPE_ID, RISK_FACTOR_ID, RISK_FACTOR, CNO ";
        if(!empty($ddlRiskFactorSV)) {
            $SQL .= ", UNO";
        }
        $SQL .= ") ";
        $SQL .= "VALUES (SEQ_ASSESSMENT_ITEM_ID.NEXTVAL, :assessmentId, :funcNo, :workTypeId, :riskFactorId, :riskFactor, :cno ";
        if(!empty($ddlRiskFactorSV)) {
            $SQL .= ", :uno";
        }
        $SQL .= ") ";
        $params = array(
            ":assessmentId" => $assessmentId,
            ":funcNo" => $riskFactorFuncNo,
            ":workTypeId" => $selWorkTypeIdList[$i],
            ":riskFactorId" => $selRiskFactorIdList[$i],
            ":riskFactor" => trim($selRiskFactorList[$i]),
            ":cno" => $riskFactorSubcon,
            ":uno" => $ddlRiskFactorSV
        );
        if (!$db->query($SQL, $params)) {
            $proceed = false;
        }
    }

    if ($proceed) {
        $msg = "저장되었습니다.";
    }
    else {
        $msg = "저장 실패하였습니다.";
    }

    $assessmentList = getAssessmentList($assessmentId, $jno, $cno, $funcNo);

    $result = array(
        "proceed" => $proceed,
        "msg" => $msg,
        "assessmentList" => $assessmentList,
        "userList" => getUserList()
    );

    echo json_encode($result);
}
//삭제
else if ("DEL" == $mode) {
    $assessmentItemId = $_POST["assessmentItemId"];

    $proceed = false;
    $SQL  = "DELETE RISK_ASSESSMENT_DETAIL ";
    $SQL .= "WHERE ASSESSMENT_ITEM_ID = :assessmentItemId ";
    $params = array(
        ":assessmentItemId" => $assessmentItemId
    );
    if ($db->query($SQL, $params)) {
        $proceed = true;
    }

    if ($proceed) {
        $proceed = false;
        $SQL  = "DELETE FROM RISK_ASSESSMENT_ACTION ";
        $SQL .= "WHERE ASSESSMENT_ITEM_ID = :assessmentItemId ";
        $params = array(
            ":assessmentItemId" => $assessmentItemId
        );
        if ($db->query($SQL, $params)) {
            $proceed = true;
        }
    }

    if ($proceed) {
        $msg = "삭제되었습니다.";
    }
    else {
        $msg = "삭제 실패하였습니다.";
    }

    $result = array(
        "proceed" => $proceed,
        "msg" => $msg
    );

    echo json_encode($result);
}
// 위험성 평가 항목 저장
else if ("SAVE" == $mode) {
    $saveAllElement = json_decode($_POST["saveAllElement"], true);
    $saveAllAction = json_decode( preg_replace('/[\x00-\x1F\x7F]/u', '', $_POST["saveAllAction"]), true );

    $proceed = false;
    $falseCnt = 0;
    $msg = '';
    foreach($saveAllElement as $itemId => $info) {
        $assessmentItemId = $itemId;
        $location = $info["location"];
        $equipment = $info["equipment"];
        $riskType = $info["ddlRiskType"];
        $frequency = $info["ddlFrequency"];
        $strength = $info["ddlStrength"];
        $isCheck = $info["appIsCheck"];
        $actionDeadline = $info["actionDeadline"];
        $subconUserName = $info["subconUserName"];
        
        $SQL  = "UPDATE RISK_ASSESSMENT_DETAIL ";
        $SQL .= "SET LOCATION = :location, ";
        $SQL .= " EQUIPMENT = :equipment, ";
        $SQL .= " RISK_TYPE = :riskType, ";
        $SQL .= " FREQUENCY = :frequency, ";
        $SQL .= " STRENGTH = :strength, ";
        $SQL .= " IS_CHECK = :isCheck, ";
        $SQL .= " ACTION_DEADLINE = :actionDeadline, ";
        $SQL .= " SUBCON_USER_NAME = :subconUserName ";
        $SQL .= "WHERE ASSESSMENT_ITEM_ID = :assessmentItemId ";
        $params = array(
            ":location" => $location,
            ":equipment" => $equipment,
            ":riskFactor" => $riskFactor,
            ":riskType" => $riskType,
            ":frequency" => $frequency,
            ":strength" => $strength,
            ":isCheck" => $isCheck,
            ":actionDeadline" => $actionDeadline,
            ":subconUserName" => $subconUserName,
            ":assessmentItemId" => $assessmentItemId
        );
        if (!$db->query($SQL, $params)) {
            $falseCnt++;
        }
    }

    if($falseCnt == 0) {
        $proceed = true;
    }
    
    if($proceed) {
        $falseCnt = 0;
        foreach($saveAllAction as $itemId => $authList) {
            foreach($authList as $auth => $info) {
                $auth = strtoupper($auth);
                if(isset($info["uno"])) {
                    $uno = $info["uno"];
                } else {
                    $uno = '';
                }

                if(isset($info["action"])) {
                    $action = $info["action"];
                } else {
                    $action = '';
                }

                $cno = '';
                if($auth == "SUBCONTRACTOR") {
                    $params = array();
                    $SQL = "SELECT CNO
                            FROM RISK_ASSESSMENT_DETAIL
                            WHERE ASSESSMENT_ITEM_ID = :assessmentItemId";
                    $params = array(
                        ":assessmentItemId" => $assessmentItemId
                    );
                    if($db->query($SQL, $params)) {
                        $db->next_record();
            
                        $row = $db->Record;
            
                        $cno = $row["cno"];
                    }
                } else if($auth == "SUPERVISOR") {
                    if(empty($uno)) {
                        $params = array();
                        $SQL = "SELECT UNO
                                FROM RISK_ASSESSMENT_DETAIL
                                WHERE ASSESSMENT_ITEM_ID = :assessmentItemId";
                        $params = array(
                            ":assessmentItemId" => $assessmentItemId
                        );
                        if($db->query($SQL, $params)) {
                            $db->next_record();
                
                            $row = $db->Record;
                
                            $uno = $row["uno"];
                        }
                    } else {
                        $params = array();
                        $SQL = "UPDATE RISK_ASSESSMENT_DETAIL
                                SET UNO = :uno
                                WHERE ASSESSMENT_ITEM_ID = :assessmentItemId";
                        $params = array(
                            ":assessmentItemId" => $assessmentItemId,
                            ":uno" => $uno
                        );
                        $db->query($SQL, $params);
                    }
                }

                $params = array();
                $SQL  = "MERGE INTO RISK_ASSESSMENT_ACTION ";
                $SQL .= "USING DUAL ";
                $SQL .= "ON (ASSESSMENT_ITEM_ID = :assessmentItemId AND AUTH = :auth) ";
                $SQL .= "WHEN MATCHED THEN ";
                $SQL .= " UPDATE SET ACTION = :action, UNO = :uno, CNO = :cno, MOD_USER = :modUser, MOD_DATE = SYSTIMESTAMP ";
                if (empty($action)) {
                    $SQL .= " DELETE WHERE ASSESSMENT_ITEM_ID = :assessmentItemId AND AUTH = :auth ";
                }
                else {
                    $SQL .= "WHEN NOT MATCHED THEN ";
                    $SQL .= " INSERT (ASSESSMENT_ITEM_ID, ASSESSMENT_ID, AUTH, ACTION, UNO, CNO, REG_USER, REG_DATE, MOD_USER, MOD_DATE) ";
                    $SQL .= " VALUES (:assessmentItemId, :assessmentId, :auth, :action, :uno, :cno, :regUser, SYSTIMESTAMP, :modUser, SYSTIMESTAMP) ";
                }
                $params = array(
                    ":assessmentItemId" => $assessmentItemId,
                    ":assessmentId" => $assessmentId,
                    ":auth" => $auth,
                    ":action" => $action,
                    ":uno" => $uno,
                    ":cno" => $cno,
                    ":regUser" => $user->uno,
                    ":modUser" => $user->uno
                );
                if (!$db->query($SQL, $params)) {
                    $falseCnt++;
                }
            }
        }

        if($falseCnt == 0) {
            $proceed = true;
            $msg = '저장되었습니다.';
        } else {
            $proceed = false;
            $msg = "저장 실패하였습니다.";
        }
    }

    $auth = $_POST["auth"];
    $cno = $_POST["ddlSubcon"];
    $assessmentList = getAssessmentList($assessmentId, $jno, $cno, $funcNo);

    $result = array(
        "proceed" => $proceed,
        "msg" => $msg,
        "assessmentList" => $assessmentList,
        "userList" => getUserList()
    );

    echo json_encode($result);
}
//위험성 평가 항목 상세
else if ("DETAIL" == $mode) {
    // $assessmentItemId = $_POST["assessmentItemId"];
    $auth = $_POST["auth"];

    // $assessmentItemInfo = getAssessmentItemInfo($assessmentItemId, $jno, $auth);

    //결재자 목록
    $userList = array();
    $SQL  = "SELECT M.AUTH, M.UNO, U.USER_NAME ";
    $SQL .= "FROM JOB_MANAGER M ";
    $SQL .= " JOIN S_SYS_USER_SET U ON M.UNO = U.UNO ";
    $SQL .= " JOIN JOB_MANAGER_FUNC F ON M.JNO = F.JNO AND M.UNO = F.UNO ";
    $SQL .= "WHERE M.JNO = :jno ";
    $SQL .= " AND M.AUTH = 'SUPERVISOR' ";
    $SQL .= " AND F.FUNC_NO = :funcNo ";
    $SQL .= "UNION ";
    $SQL .= "SELECT M.AUTH, M.UNO, U.USER_NAME ";
    $SQL .= "FROM JOB_MANAGER M ";
    $SQL .= " JOIN S_SYS_USER_SET U ON M.UNO = U.UNO ";
    $SQL .= "WHERE M.JNO = :jno ";
    $SQL .= " AND M.AUTH = 'SAFETY_MANAGER' ";
    // $SQL .= " AND M.TEAM_LEADER = 'Y' ";
    $SQL .= "UNION ";
    $SQL .= "SELECT M.AUTH, M.UNO, U.USER_NAME ";
    $SQL .= "FROM JOB_MANAGER M ";
    $SQL .= " JOIN S_SYS_USER_SET U ON M.UNO = U.UNO ";
    $SQL .= "WHERE M.JNO = :jno ";
    $SQL .= " AND M.AUTH = 'SUPERINTENDENT' ";
    $params = array(
        ":jno" => $jno,
        ":funcNo" => $assessmentItemInfo["funcNo"]
    );
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        $userList[strtolower($row["auth"])][] = array(
            "uno" => $row["uno"], 
            "userName" => $row["user_name"] 
        );
    }

    $result = array(
        // "assessmentItemInfo" => $assessmentItemInfo,
        "userList" => $userList
    );

    echo json_encode($result);
}
else if ("SAVE_APP_ACTION" == $mode) {
    $assessmentItemId = $_POST["assessmentItemId"];
    $action = trim($_POST["actionText"]);
    $auth = strtoupper($_POST["eachAuth"]);
    $appStaff = $_POST["appStaff"];
    $cno = '';
    
    $proceed = true;
    
    $params = array();
    $SQL = "SELECT FUNC_NO 
            FROM RISK_ASSESSMENT_DETAIL
            WHERE ASSESSMENT_ITEM_ID = :assessmentItemId";
    $params = array(
        ":assessmentItemId" => $assessmentItemId
    );
    $db->query($SQL, $params);
    $db->next_record();
    $row = $db->Record;
    $tempFuncNo = $row["func_no"];
    if(empty($appStaff)) {
        $SQL = "WITH T_AU AS (
                                SELECT APPROVER_UNO 
                                FROM RISK_APPROVAL_TARGET T
                                RIGHT OUTER JOIN APPROVAL_INFO A ON A.APPROVAL_TARGET_ID = T.APPROVAL_TARGET_ID
                                WHERE ASSESSMENT_ID = :assessment_id
                                AND T.FUNC_NO = :funcNo
                            ) ";
        $SQL .= "SELECT M.AUTH, M.UNO, U.USER_NAME, M.TEAM_LEADER, ";
        $SQL .= "CASE WHEN M.UNO = :loginUno THEN 1 WHEN A.APPROVER_UNO = M.UNO THEN 2 ELSE 3 END AS LOGIN_USER ";
        $SQL .= "FROM JOB_MANAGER M ";
        $SQL .= " JOIN S_SYS_USER_SET U ON M.UNO = U.UNO ";
        $SQL .= " JOIN JOB_MANAGER_FUNC F ON M.JNO = F.JNO AND M.UNO = F.UNO ";
        $SQL .= "LEFT OUTER JOIN T_AU A ON A.APPROVER_UNO = M.UNO ";
        $SQL .= "WHERE M.JNO = :jno ";
        $SQL .= " AND M.AUTH = :auth ";
        $SQL .= " AND F.FUNC_NO = :funcNo ";
        $SQL .= " ORDER BY LOGIN_USER, TEAM_LEADER DESC";
        $params = array(
            ":jno" => $jno,
            ":funcNo" => $tempFuncNo,
            ":loginUno" => $_SESSION["user"]["uno"],
            ":auth" => $auth,
            ":assessment_id" => $assessmentId
        );
        if($db->query($SQL, $params)) {
            $db->next_record();
            $row = $db->Record;

            $appStaff = $row["uno"];
        }
    }
    if($auth == "SUPERVISOR") {
        $SQL = "UPDATE RISK_ASSESSMENT_DETAIL
                SET UNO = :uno
                WHERE ASSESSMENT_ITEM_ID = :assessmentItemId";
        $params = array(
            ":assessmentItemId" => $assessmentItemId,
            ":uno" => $appStaff
        );
        if($db->query($SQL, $params)) {
            $proceed = true;
        } else {
            $proceed = false;
        }
    } else if($auth == "SUBCONTRACTOR") {
        $params = array();
        $SQL = "SELECT CNO
                FROM RISK_ASSESSMENT_DETAIL
                WHERE ASSESSMENT_ITEM_ID = :assessmentItemId";
        $params = array(
            ":assessmentItemId" => $assessmentItemId
        );
        if($db->query($SQL, $params)) {
            $db->next_record();

            $row = $db->Record;

            $cno = $row["cno"];
        } else {
            $proceed = false;
        }
    }

    if($proceed) {
        $SQL  = "MERGE INTO RISK_ASSESSMENT_ACTION ";
        $SQL .= "USING DUAL ";
        $SQL .= "ON (ASSESSMENT_ITEM_ID = :assessmentItemId AND AUTH = :auth) ";
        $SQL .= "WHEN MATCHED THEN ";
        $SQL .= " UPDATE SET ACTION = :action, UNO = :uno, CNO = :cno, MOD_USER = :modUser, MOD_DATE = SYSTIMESTAMP ";
        if (empty($action)) {
            $SQL .= " DELETE WHERE ASSESSMENT_ITEM_ID = :assessmentItemId AND AUTH = :auth ";
        }
        else {
            $SQL .= "WHEN NOT MATCHED THEN ";
            $SQL .= " INSERT (ASSESSMENT_ITEM_ID, ASSESSMENT_ID, AUTH, ACTION, UNO, CNO, REG_USER, REG_DATE, MOD_USER, MOD_DATE) ";
            $SQL .= " VALUES (:assessmentItemId, :assessmentId, :auth, :action, :uno, :cno, :regUser, SYSTIMESTAMP, :modUser, SYSTIMESTAMP) ";
        }
        $params = array(
            ":assessmentItemId" => $assessmentItemId,
            ":assessmentId" => $assessmentId,
            ":auth" => $auth,
            ":action" => $action,
            ":uno" => $appStaff,
            ":cno" => $cno,
            ":regUser" => $user->uno,
            ":modUser" => $user->uno
        );
        if ($db->query($SQL, $params)) {
            $proceed = true;
        }
        else {
            $proceed = false;
        }
    }

    if ($proceed) {
        $msg = "저장되었습니다.";
    }
    else {
        $msg = "저장 실패하였습니다.";
    }

    // $assessmentList = getAssessmentList($assessmentId, $jno, $cno, $funcNo);
    $assessmentItemInfo = getAssessmentItemInfo($assessmentItemId, $jno, $auth);

    $result = array(
        "proceed" => $proceed,
        "msg" => $msg,
        "assessmentItemInfo" => $assessmentItemInfo,
        "userList" => getUserList($tempFuncNo),
        "loginUserNm" => $user->uno
    );

    echo json_encode($result);
}
//회의대상 저장
else if ("SAVE_TARGET" == $mode) {
    $assessmentItemId = $_POST["assessmentItemId"];
    $isCheck = $_POST["appIsCheck_" . $assessmentItemId];

    $proceed = false;
    $SQL  = "UPDATE RISK_ASSESSMENT_DETAIL ";
    $SQL .= "SET IS_CHECK = :isCheck ";
    $SQL .= "WHERE ASSESSMENT_ITEM_ID = :assessmentItemId ";
    $params = array(
        ":isCheck" => $isCheck,
        ":assessmentItemId" => $assessmentItemId
    );
    if ($db->query($SQL, $params)) {
        $proceed = true;
    }

    $result = array(
        "proceed" => $proceed
    );

    echo json_encode($result);
}
//위험요인 - 공종 선택
else if ("LIST_FUNC" == $mode) {
    $riskFactorSubcon = $_POST["ddlRiskFactorSubcon"];
    $funcNoList = array();
    $SQL  = "SELECT P.FUNC_NO, F.FUNC_NAME ";
    $SQL .= "FROM RISK_APPROVAL_TARGET P ";
    $SQL .= " JOIN COMMON.COMM_FUNC_QHSE F ON P.FUNC_NO = F.FUNC_NO ";
    $SQL .= "WHERE P.ASSESSMENT_ID = :assessmentId ";
    $SQL .= " AND P.CNO = :cno ";
    $SQL .= "ORDER BY F.SORT_NO ";
    $params = array(
        ":assessmentId" => $assessmentId,
        ":cno" => $riskFactorSubcon
    );
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        if (empty($funcNo)) {
            $funcNo = $row["func_no"];
        }
        $funcNoList[] = array(
            "funcNo" => $row["func_no"], 
            "funcName" => $row["func_name"] 
        );
    }

    $result = array(
        "funcNoList" => $funcNoList
    );

    echo json_encode($result);
}
//협력업체 선택, 공종 선택
else if ("LIST_BY_SUBCON" == $mode || "LIST_BY_FUNC" == $mode) {
    $auth = $_POST["auth"];

    $funcNoList = array();
    //협력업체 선택
    if ("LIST_BY_SUBCON" == $mode) {
        if($auth == "SUPERVISOR") {
            $SQL = "SELECT T.CNO, S.COMP_NAME, T.FUNC_NO, F.FUNC_NAME FROM RISK_APPROVAL_TARGET T
            INNER JOIN JOB_SUBCON_INFO S ON T.CNO = S.CNO
            INNER JOIN COMMON.COMM_FUNC_QHSE F ON T.FUNC_NO = F.FUNC_NO
            WHERE T.ASSESSMENT_ID = :assessmentId
            AND T.APPROVAL_STATUS = :approvalStatus
            AND T.FUNC_NO IN (SELECT FUNC_NO FROM JOB_MANAGER_FUNC WHERE JNO = :jno AND UNO = :uno)";
            $params = array(
                ":assessmentId" => $assessmentId,
                ":approvalStatus" => 10,
                ":jno" => $jno,
                ":uno" => $user->uno
            );
            $db->query($SQL, $params);
            while($db->next_record()) {
                $row = $db->Record;

                $funcNoList[] = array(
                    "funcNo" => $row["func_no"], 
                    "funcName" => $row["func_name"] 
                );

                $funcNoList = array_values(array_filter($funcNoList, function ($item, $key) use ($funcNoList) {
                    return !isset($funcNoList[$key + 1]) || $item['funcNo'] !== $funcNoList[$key + 1]['funcNo'];
                }, ARRAY_FILTER_USE_BOTH));
            }
        } else if (!empty($cno)) {
            $SQL  = "SELECT P.FUNC_NO, F.FUNC_NAME ";
            $SQL .= "FROM RISK_APPROVAL_TARGET P ";
            $SQL .= " JOIN COMMON.COMM_FUNC_QHSE F ON P.FUNC_NO = F.FUNC_NO ";
            $SQL .= "WHERE P.ASSESSMENT_ID = :assessmentId ";
            $SQL .= " AND P.CNO = :cno ";
            $SQL .= "ORDER BY F.SORT_NO ";
            $params = array(
                ":assessmentId" => $assessmentId,
                ":cno" => $cno
            );
            $db->query($SQL, $params);
            while($db->next_record()) {
                $row = $db->Record;

                if (empty($funcNo)) {
                    $funcNo = $row["func_no"];
                }
                $funcNoList[] = array(
                    "funcNo" => $row["func_no"], 
                    "funcName" => $row["func_name"]
                );
            }
        }
    }

    // $approvalInfo = array();
    // if (empty($funcNo)) {
    //     $SQL  = "WITH T_APP AS ( ";
    //     $SQL .= "SELECT APPROVAL_STATUS, COUNT(APPROVAL_STATUS) AS CNT ";
    //     $SQL .= "FROM RISK_APPROVAL_TARGET ";
    //     $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
    //     $SQL .= "GROUP BY ASSESSMENT_ID, APPROVAL_STATUS ";
    //     $SQL .= ") ";
    //     $SQL .= "SELECT A.APPROVAL_STATUS, S.CD_NM, A.CNT ";
    //     $SQL .= "FROM T_APP A ";
    //     $SQL .= " JOIN RISK_CODE_SET S ON S.MAJOR_CD = 'APPROVAL_STATUS' AND A.APPROVAL_STATUS = S.MINOR_CD ";
    //         $params = array(
    //         ":assessmentId" => $assessmentId
    //     );
    //     $db->query($SQL, $params);
    //     while($db->next_record()) {
    //         $row = $db->Record;
    
    //         $approvalStatusList[$row["approval_status"]] = $row["cd_nm"] . "(" . $row["cnt"] . ")";
    //     }
    //     $showButton = false;
    //     if (count($approvalStatusList) == 1) {
    //         if ($auth == "SAFETY_MANAGER") {
    //             if (array_key_exists("20", $approvalStatusList)) {
    //                 $showButton = true;
    //             }
    //         }
    //         else if ($auth == "SUPERINTENDENT") {
    //             if (array_key_exists("30", $approvalStatusList)) {
    //                 $showButton = true;
    //             }
    //         }
    //     }
    //     $approvalInfo = array(
    //         "showButton" => $showButton, 
    //         "approvalTargetId" => "", 
    //         "approvalStatusName" => implode(",", $approvalStatusList)
    //     );
    // }
    // else {
    //     $SQL  = "SELECT A.APPROVAL_TARGET_ID, A.APPROVAL_STATUS, C.CD_NM ";
    //     $SQL .= "FROM RISK_APPROVAL_TARGET A ";
    //     $SQL .= " LEFT OUTER JOIN RISK_CODE_SET C ON C.MAJOR_CD = 'APPROVAL_STATUS' AND A.APPROVAL_STATUS = C.MINOR_CD ";
    //     $SQL .= "WHERE A.ASSESSMENT_ID = :assessmentId ";
    //     $SQL .= " AND A.CNO = :cno ";
    //     $SQL .= " AND A.FUNC_NO = :funcNo ";
    //     $params = array(
    //         ":assessmentId" => $assessmentId,
    //         ":cno" => $cno,
    //         ":funcNo" => $funcNo
    //     );
    //     $db->query($SQL, $params);
    //     $db->next_record();
    //     $row = $db->Record;
    //     $showButton = false;
    //     if ($auth == "SUPERVISOR") {
    //         if ("10" == $row["approval_status"]) {
    //             $showButton = true;
    //         }
    //     }
    //     else if ($auth == "SAFETY_MANAGER") {
    //         if ("20" == $row["approval_status"]) {
    //             $showButton = true;
    //         }
    //     }
    //     else if ($auth == "SUPERINTENDENT") {
    //         if ("30" == $row["approval_status"]) {
    //             $showButton = true;
    //         }
    //     }
    //     $approvalInfo = array(
    //         "showButton" => $showButton, 
    //         "approvalTargetId" => $row["approval_target_id"], 
    //         "approvalStatusName" => $row["cd_nm"]
    //     );
    // }

    //진행상황
    $tempAppStatus = array();
    $approvalStatusList = array();
    $SQL  = "SELECT P.APPROVAL_TARGET_ID, S.CNO, S.COMP_NAME, P.FUNC_NO, F.FUNC_NAME, P.APPROVAL_STATUS, C.CD_NM AS APPROVAL_STATUS_NAME, C.VAL5 ";
    $SQL .= "FROM RISK_APPROVAL_TARGET P ";
    $SQL .= " JOIN RISK_CODE_SET C ON C.MAJOR_CD = 'APPROVAL_STATUS' AND P.APPROVAL_STATUS = C.MINOR_CD ";
    $SQL .= " JOIN JOB_SUBCON_INFO S ON P.CNO = S.CNO AND S.JNO = :jno ";
    $SQL .= " JOIN COMMON.COMM_FUNC_QHSE F ON P.FUNC_NO = F.FUNC_NO ";
    $SQL .= "WHERE P.ASSESSMENT_ID = :assessmentId ";
    $SQL .= " AND P.APPROVAL_TYPE = :approvalType ";
    if (!empty($cno) && !empty($funcNo)) {
        $SQL .= " AND P.CNO = :cno ";
        $SQL .= " AND P.FUNC_NO = :funcNo ";
    }
    $SQL .= "ORDER BY C.VAL5, F.SORT_NO ";
    $params = array(
        ":jno" => $jno,
        ":assessmentId" => $assessmentId,
        ":approvalType" => "ASMT_REP",
        ":cno" => $cno,
        ":funcNo" => $funcNo
    );
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        if($auth == "SUPERVISOR") {
            if($funcNo == $row["func_no"]) {
                $tempAppStatus[] = $row["approval_status"];
            }
        } else {
            $tempAppStatus[] = $row["approval_status"];
        }
        $approvalStatusList[$row["val5"]]["name"] = $row["approval_status_name"];
        $approvalStatusList[$row["val5"]]["funcList"][] = array(
            "funcName" => $row["comp_name"] . "(" . $row["func_name"] . ")",
            "approvalTargetId" => $row["approval_target_id"]
        );
    }

    $showButton = false;
    $tempAppStatus = array_unique($tempAppStatus);

    // if (count($tempAppStatus) == 1) {
        //관리감독자
        if ($auth == "SUPERVISOR") {
            if (in_array(APP_STATUS_SBM, $tempAppStatus)) {
                $showButton = true;
            }
        }
        //안전관리자
        else if ($auth == "SAFETY_MANAGER") {
            //작성완료일 경우
            if (in_array(APP_STATUS_APP_L1, $tempAppStatus)) {
                $showButton = true;
            }
        }
        //현장소장
        else if ($auth == "SUPERINTENDENT") {
            //검토자승인일 경우
            if (in_array(APP_STATUS_APP_L2, $tempAppStatus)) {
                $showButton = true;
            }
        }
    // }
    $approvalInfo = array(
        "showButton" => $showButton, 
        "approvalStatusList" => $approvalStatusList
    );

    $approvalList = array();
    $SQL  = "SELECT M.AUTH, M.UNO, U.USER_NAME, M.TEAM_LEADER ";
    $SQL .= "FROM JOB_MANAGER M ";
    $SQL .= " JOIN S_SYS_USER_SET U ON M.UNO = U.UNO ";
    $SQL .= " LEFT OUTER JOIN JOB_MANAGER_FUNC F ON M.JNO = F.JNO AND M.UNO = F.UNO ";
    $SQL .= "WHERE M.JNO = :jno ";
    $SQL .= " AND M.AUTH = :auth ";
    if ($auth == "SUPERVISOR") {
        $SQL .= " AND F.FUNC_NO = :funcNo ";
    }
    $params = array(
        ":jno" => $jno,
        ":auth" => $auth,
        ":funcNo" => $funcNo
    );
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        if ($auth == "SAFETY_MANAGER") {
            $selected = ($row["team_leader"] == "Y");
        }
        else {
            $selected = ($row["uno"] == $user->uno);
        }
        $approvalList[] = array(
            "uno" => $row["uno"], 
            "userName" => $row["user_name"], 
            "selected" => $selected
        );
    }

    $assessmentList = getAssessmentList($assessmentId, $jno, $cno, $funcNo);

    $result = array(
        "funcNoList" => $funcNoList,
        "approvalInfo" => $approvalInfo,
        "approvalList" => $approvalList,
        "assessmentList" => $assessmentList,
        "userList" => getUserList()
    );

    echo json_encode($result);
}
//초기화면 표시
else if ("INIT" == $mode) {
    $approvalTargetId = $_POST["approvalTargetId"];

    //협력업체 목록
    $subconList = array();
    //공종(현장) 목록
    $funcNoList = array();
    if ($_SESSION["risk"]["auth"] == "SUPERVISOR") {
        $SQL = "SELECT T.CNO, S.COMP_NAME, T.FUNC_NO, F.FUNC_NAME 
                FROM RISK_APPROVAL_TARGET T
                INNER JOIN JOB_SUBCON_INFO S ON T.CNO = S.CNO
                INNER JOIN COMMON.COMM_FUNC_QHSE F ON T.FUNC_NO = F.FUNC_NO
                WHERE T.ASSESSMENT_ID = :assessmentId
                AND T.APPROVAL_STATUS = :approvalStatus
                AND T.FUNC_NO IN (SELECT FUNC_NO FROM JOB_MANAGER_FUNC WHERE JNO = :jno AND UNO = :uno)";
        $params = array(
            ":assessmentId" => $assessmentId,
            ":approvalStatus" => 10,
            ":jno" => $jno,
            ":uno" => $user->uno
        );
        $db->query($SQL, $params);
        while($db->next_record()) {
            $row = $db->Record;
            
            $subconList[] = array(
                "cno" => $row["cno"], 
                "compName" => $row["comp_name"] 
            );

            $funcNoList[] = array(
                "funcNo" => $row["func_no"], 
                "funcName" => $row["func_name"] 
            );

            $funcNoList = array_values(array_filter($funcNoList, function ($item, $key) use ($funcNoList) {
                return !isset($funcNoList[$key + 1]) || $item['funcNo'] !== $funcNoList[$key + 1]['funcNo'];
            }, ARRAY_FILTER_USE_BOTH));
        }

        $subconList = array_reduce($subconList, function($carry, $item) {
            if (!array_key_exists($item["cno"], $carry)) {
                $carry[$item["cno"]] = $item;
            }
            return $carry;
        }, array());
        
        $subconList = array_values($subconList);
    }
    else {
        $SQL  = "WITH T_APP AS ( ";
        $SQL .= "SELECT DISTINCT CNO ";
        $SQL .= "FROM RISK_APPROVAL_TARGET ";
        $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
        $SQL .= " AND APPROVAL_TYPE = 'ASMT_REP' ";
        $SQL .= ") ";
        $SQL .= "SELECT P.CNO, S.COMP_NAME ";
        $SQL .= "FROM T_APP P ";
        $SQL .= " JOIN JOB_SUBCON_INFO S ON P.CNO = S.CNO AND S.JNO = :jno ";
        $SQL .= "ORDER BY P.CNO ";
        $params = array(
            ":assessmentId" => $assessmentId,
            ":jno" => $jno
        );
        $db->query($SQL, $params);
        while($db->next_record()) {
            $row = $db->Record;
    
            $subconList[] = array(
                "cno" => $row["cno"], 
                "compName" => $row["comp_name"] 
            );
        }
    }

    //위험성 평가 정보
    $SQL  = "WITH T_C AS ( ";
    $SQL .= "SELECT MINOR_CD, CD_NM ";
    $SQL .= "FROM RISK_CODE_SET ";
    $SQL .= "WHERE MAJOR_CD = 'ASSESSMENT_TYPE' ";
    $SQL .= " AND IS_USE = 'Y' ";
    $SQL .= ") ";
    $SQL .= "SELECT I.SEQ, I.ASSESSMENT_TYPE, T_C.CD_NM AS ASSESSMENT_TYPE_NAME, TO_CHAR(I.ASSESSMENT_DATE, 'YYYY-MM-DD') AS ASSESSMENT_DATE, TO_CHAR(I.START_DATE, 'YYYY-MM-DD') AS START_DATE, TO_CHAR(I.END_DATE, 'YYYY-MM-DD') AS END_DATE ";
    $SQL .= "FROM RISK_ASSESSMENT_INFO I ";
    $SQL .= " JOIN T_C ON I.ASSESSMENT_TYPE = T_C.MINOR_CD ";
    $SQL .= "WHERE I.ASSESSMENT_ID = :assessmentId ";
    $params = array(
        ":assessmentId" => $assessmentId
    );
    $db->query($SQL, $params);
    $db->next_record();
    $row = $db->Record;
    $assessmentInfo = array(
        "seq" => $row["seq"],
        "assessmentTypeName" => $row["assessment_type_name"],
        "assessmentDate" => $row["assessment_date"],
        "assessmentTerm" => $row["start_date"] . "~" . $row["end_date"]
    );

    //권한
    $authList = array();
    $SQL  = "SELECT MINOR_CD, CD_NM ";
    $SQL .= "FROM RISK_CODE_SET ";
    $SQL .= "WHERE MAJOR_CD = 'AUTH' ";
    $SQL .= " AND IS_USE = 'Y' ";
    $SQL .= " AND VAL2 = 'ASMT_REP' ";
    $SQL .= "ORDER BY VAL5 ";
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        $authList[] = array(
            "code" => strtolower($row["minor_cd"]),
            "name" => $row["cd_nm"]
        );
    }

    $result = array(
        "auth" => $_SESSION["risk"]["auth"],
        "dangerHazardLevel" => $dangerHazardLevel,
        "subconList" => $subconList,
        "funcNoList" => $funcNoList,
        "assessmentInfo" => $assessmentInfo,
        "authList" => $authList,
        "riskTypeList" => getRiskTypeList(),
        "frequencyList" => getFrequencyList(),
        "strengthList" => getStrengthList()
    );

    echo json_encode($result);
}
// 개별 저장
else if ($mode == "EACH_SAVE") {
    $assessmentItemId = $_POST["assessmentItemId"];
    $eachKey = $_POST["eachKey"];
    $eachVal = $_POST["eachVal"];

    switch ( $eachKey ):
        case "ddlRiskType":
            $eachKey = "risk_type";
            break;
        case "ddlFrequency":
            $eachKey = "frequency";
            break;
        case "ddlStrength";
            $eachKey = "strength";
            break;
        case "actionDeadline";
            $eachKey = "action_deadline";
            break;
        case "subconUserName";
            $eachKey = "subcon_user_name";
            break;
    endswitch;

    $eachKey = strtolower($eachKey);

    $proceed = false;
    $params= array();
    if(strpos($eachKey,'uno')) {
        $auth = str_replace("uno", "", $eachKey);
        $authUpper = strtoupper($auth);

        $SQL = "UPDATE RISK_ASSESSMENT_ACTION
                SET UNO = :uno
                WHERE ASSESSMENT_ITEM_ID = :assessmentItemId 
                AND AUTH = :auth";
        $params = array(
            ":auth" => $authUpper,
            ":assessmentItemId" => $assessmentItemId,
            ":uno" => $eachVal
        );

        if($db->query($SQL, $params)) {
            $proceed = true;
        }

        if($proceed) {
            if($authUpper == "SUPERVISOR") {
                $SQL = "UPDATE RISK_ASSESSMENT_DETAIL
                        SET UNO = :uno
                        WHERE ASSESSMENT_ITEM_ID = :assessmentItemId";
                if($db->query($SQL, $params)) {
                    $proceed = true;
                } else {
                    $proceed = false;
                }
            }
        }
    } else {
        $SQL = "UPDATE RISK_ASSESSMENT_DETAIL
                SET {$eachKey} = :eachVal
                WHERE ASSESSMENT_ITEM_ID = :assessmentItemId";
        $params = array(
            ":eachVal" => $eachVal,
            ":assessmentItemId" => $assessmentItemId
        );
        if($db->query($SQL, $params)) {
            $proceed = true;
        }
    }

    $auth = $_POST["auth"];

    // $assessmentItemInfo = getAssessmentItemInfo($assessmentItemId, $jno, $auth);

    $result = array(
        "proceed" => $proceed,
        // "assessmentItemInfo" => $assessmentItemInfo,
        // "userList" => getUserList()
    );

    echo json_encode($result);
}

function getAssessmentItemInfo($assessmentItemId, $jno, $auth) {
    global $db;
    global $dangerHazardLevel;
    global $funcColorList;
    global $user;

    $assessmentItemInfo = array();
    $SQL  = "WITH T_A AS ( ";
    $SQL .= "SELECT ASSESSMENT_ITEM_ID, SUBCONTRACTOR_UNO, SUBCONTRACTOR_USER_NAME, SUBCONTRACTOR_ACTION, ";
    $SQL .= " SUPERVISOR_UNO, SUPERVISOR_USER_NAME, SUPERVISOR_ACTION, SAFETY_MANAGER_UNO, SAFETY_MANAGER_USER_NAME, SAFETY_MANAGER_ACTION, ";
    $SQL .= " SUPERINTENDENT_UNO, SUPERINTENDENT_USER_NAME, SUPERINTENDENT_ACTION ";
    $SQL .= "FROM ( ";
    $SQL .= " SELECT A.ASSESSMENT_ITEM_ID, A.AUTH, A.UNO, U.USER_NAME, A.ACTION ";
    $SQL .= " FROM RISK_ASSESSMENT_ACTION A ";
    $SQL .= "  LEFT OUTER JOIN S_SYS_USER_SET U ON A.UNO = U.UNO ";
    $SQL .= " WHERE A.ASSESSMENT_ITEM_ID = :assessmentItemId ";
    $SQL .= "  AND A.AUTH IN (SELECT MINOR_CD FROM RISK_CODE_SET WHERE MAJOR_CD = 'AUTH' AND IS_USE = 'Y' AND VAL1 = 'HEAD') ";
    $SQL .= " UNION ";
    $SQL .= " SELECT A.ASSESSMENT_ITEM_ID, A.AUTH, A.CNO, S.COMP_NAME, A.ACTION ";
    $SQL .= " FROM RISK_ASSESSMENT_ACTION A ";
    $SQL .= "  LEFT OUTER JOIN JOB_SUBCON_INFO S ON A.CNO = S.CNO AND S.JNO = :jno ";
    $SQL .= " WHERE A.ASSESSMENT_ITEM_ID = :assessmentItemId ";
    $SQL .= "  AND A.AUTH = 'SUBCONTRACTOR' ";
    $SQL .= ") ";
    $SQL .= "PIVOT ( ";
    $SQL .= " MAX(UNO) AS UNO, MAX(USER_NAME) AS USER_NAME, MAX(ACTION) AS ACTION FOR AUTH IN ('SUBCONTRACTOR' AS SUBCONTRACTOR, 'SUPERVISOR' AS SUPERVISOR, 'SAFETY_MANAGER' AS SAFETY_MANAGER, 'SUPERINTENDENT' AS SUPERINTENDENT) ";
    $SQL .= ") ";
    $SQL .= "), ";
    $SQL .= "T_W AS ( ";
    $SQL .= "SELECT WORK_TYPE_ID, SUBSTR(SYS_CONNECT_BY_PATH(WORK_TYPE_NAME, '>'), INSTR(SYS_CONNECT_BY_PATH(WORK_TYPE_NAME, '>'), '>', 1, 2) + 1) AS WORK_TYPE_PATH ";
    $SQL .= "FROM RISK_WORK_TYPE ";
    $SQL .= "WHERE CONNECT_BY_ISLEAF = 1 ";
    $SQL .= "START WITH PARENT_WORK_TYPE_ID = 0 ";
    $SQL .= "CONNECT BY PRIOR WORK_TYPE_ID = PARENT_WORK_TYPE_ID ";
    $SQL .= ") ";
    $SQL .= "SELECT D.ASSESSMENT_ITEM_ID, P.APPROVAL_STATUS, D.FUNC_NO, F.FUNC_NAME, D.LOCATION, D.EQUIPMENT, D.WORK_TYPE_ID, W.WORK_TYPE_PATH, ";
    $SQL .= " D.RISK_FACTOR_ID, D.RISK_FACTOR, D.CNO, S.COMP_NAME, A.SUBCONTRACTOR_UNO, A.SUBCONTRACTOR_USER_NAME, A.SUBCONTRACTOR_ACTION, ";
    $SQL .= " A.SUPERVISOR_UNO, A.SUPERVISOR_USER_NAME, A.SUPERVISOR_ACTION, A.SAFETY_MANAGER_UNO, A.SAFETY_MANAGER_USER_NAME, A.SAFETY_MANAGER_ACTION, ";
    $SQL .= " A.SUPERINTENDENT_UNO, A.SUPERINTENDENT_USER_NAME, A.SUPERINTENDENT_ACTION, ";
    $SQL .= " D.RISK_TYPE, T.CD_NM AS RISK_TYPE_NAME, D.FREQUENCY, D.STRENGTH, D.IS_CHECK, D.ACTION_DEADLINE, D.SUBCON_USER_NAME, D.UNO, U.USER_NAME AS APPROVER_NAME ";
    $SQL .= "FROM RISK_ASSESSMENT_DETAIL D ";
    $SQL .= " JOIN RISK_APPROVAL_TARGET P ON D.ASSESSMENT_ID = P.ASSESSMENT_ID AND P.APPROVAL_TYPE = :approvalType AND D.CNO = P.CNO AND D.FUNC_NO = P.FUNC_NO ";
    $SQL .= " JOIN COMMON.COMM_FUNC_QHSE F ON D.FUNC_NO = F.FUNC_NO ";
    $SQL .= " JOIN T_W W ON D.WORK_TYPE_ID = W.WORK_TYPE_ID ";
    $SQL .= " LEFT OUTER JOIN JOB_SUBCON_INFO S ON D.CNO = S.CNO AND S.JNO = :jno ";
    $SQL .= " LEFT OUTER JOIN S_SYS_USER_SET U ON D.UNO = U.UNO ";
    $SQL .= " LEFT OUTER JOIN T_A A ON D.ASSESSMENT_ITEM_ID = A.ASSESSMENT_ITEM_ID ";
    $SQL .= " LEFT OUTER JOIN RISK_CODE_SET T ON T.MAJOR_CD = 'RISK_TYPE' AND D.RISK_TYPE = T.MINOR_CD ";
    $SQL .= "WHERE D.ASSESSMENT_ITEM_ID = :assessmentItemId ";
    $params = array(
        ":assessmentItemId" => $assessmentItemId,
        ":jno" => $jno,
        ":approvalType" => "ASMT_REP"
    );
    $db->query($SQL, $params);
    $db->next_record();
    $row = $db->Record;

    $workTypePath = $row["work_type_path"];

    $subcontractorUno = $row["subcontractor_uno"];
    if (empty($subcontractorUno)) {
        $subcontractorUno = $row["cno"];
    }
    $subcontractorUserName = $row["subcontractor_user_name"];
    if (empty($subcontractorUserName)) {
        $subcontractorUserName = $row["comp_name"];
    }

    $supervisorUno = $row["supervisor_uno"];
    if ("SUPERVISOR" == $auth && empty($supervisorUno)) {
        $supervisorUno = $user->uno;
    }

    $rating = 0;
    if (!empty($row["frequency"]) && !empty($row["strength"])) {
        $rating = ($row["frequency"] * $row["strength"]);
    }

    $isDanger = false;
    if ($rating >= $dangerHazardLevel) {
        $isDanger = true;
    }

    $canEdit = false;
    if ($auth == "SUPERVISOR") {
        if ("10" == $row["approval_status"]) {
            $canEdit = true;
        }
    }
    else if ($auth == "SAFETY_MANAGER") {
        if ("20" == $row["approval_status"]) {
            $canEdit = true;
        }
    }
    else if ($auth == "SUPERINTENDENT") {
        if ("30" == $row["approval_status"]) {
            $canEdit = true;
        }
    }

    $assessmentItemInfo = array(
        //항목 고유번호
        "assessmentItemId" => $row["assessment_item_id"],
        //공종(현장) 고유번호
        "funcNo" => $row["func_no"], 
        //공종(현장) 배경색
        "funcColor" => $funcColorList[$row["func_no"]],
        //공종(현장)명
        "funcName" => $row["func_name"], 
        //작업단위 고유번호
        "workTypeId" => $row["work_type_id"],
        //작업단위
        "workTypePath" => $workTypePath,
        //장소/위치
        "location" => $row["location"],
        "txtLocation" => nl2br($row["location"]),
        //사용장비/도구
        "equipment" => $row["equipment"],
        "txtEquipment" => nl2br($row["equipment"]),
        //위험요인 고유번호
        "riskFactorId" => $row["risk_factor_id"],
        //위험요인
        "riskFactor" => $row["risk_factor"],
        "txtRiskFactor" => nl2br($row["risk_factor"]),
        //협력업체 고유번호
        "subcontractorUno" => $subcontractorUno, 
        //협력업체명
        "subcontractorUserName" => $subcontractorUserName, 
        //안전보건관리대책 - 협력업체
        "subcontractorAction" => $row["subcontractor_action"], 
        "subcontractorTxtAction" => nl2br($row["subcontractor_action"]), 
        //관리감독자 고유번호
        "supervisorUno" => $supervisorUno, 
        //관리감독자명
        "supervisorUserName" => $row["supervisor_user_name"], 
        //안전보건관리대책 - 관리감독자
        "supervisorAction" => $row["supervisor_action"], 
        "supervisorTxtAction" => nl2br($row["supervisor_action"]), 
        //안전관리자 고유번호
        "safety_managerUno" => $row["safety_manager_uno"], 
        //안전관리자명
        "safety_managerUserName" => $row["safety_manager_user_name"], 
        //안전보건관리대책 - 안전관리자
        "safety_managerAction" => $row["safety_manager_action"], 
        "safety_managerTxtAction" => nl2br($row["safety_manager_action"]), 
        //현장소장명
        "superintendentUserName" => $row["superintendent_user_name"], 
        //안전보건관리대책 - 현장소장
        "superintendentAction" => $row["superintendent_action"], 
        "superintendentTxtAction" => $row["superintendent_action"], 
        //재해형태
        "riskType" => $row["risk_type"],
        //재해형태명
        "riskTypeName" => $row["risk_type_name"],
        //빈도
        "frequency" => $row["frequency"],
        //강도
        "strength" => $row["strength"],
        //등급
        "rating" => $rating,
        //위험성
        "isDanger" => $isDanger,
        //회의대상
        "isCheck" => $row["is_check"],
        //조치자
        "subconUserName" => $row["subcon_user_name"], 
        //확인자
        "approverName" => $row["approver_name"], 
        //조치기한
        "actionDeadline" => $row["action_deadline"],
        //편집 가능 여부
        "canEdit" => $canEdit
    );

    return $assessmentItemInfo;
}

function getAssessmentList($assessmentId, $jno, $cno, $funcNo) {
    global $db;
    global $dangerHazardLevel;
    global $funcColorList;
    global $user;

    $auth = $_SESSION["risk"]["auth"];

    if ($auth == "SUPERVISOR") {
        $approvalStatus = 10;
    } else if ($auth == "SAFETY_MANAGER") {
        $approvalStatus = 20;
    } else if ($auth == "SUPERINTENDENT") {
        $approvalStatus = 30;
    } else {
        $approvalStatus = '';
    }

    $assessmentList = array();
    $SQL  = "WITH T_A AS ( ";
    $SQL .= "SELECT ASSESSMENT_ITEM_ID, SUBCONTRACTOR_USER_NAME, SUBCONTRACTOR_ACTION, SUPERVISOR_USER_NAME, SUPERVISOR_ACTION, SAFETY_MANAGER_USER_NAME, SAFETY_MANAGER_ACTION, SUPERINTENDENT_USER_NAME, SUPERINTENDENT_ACTION ";
    $SQL .= "FROM ( ";
    $SQL .= " SELECT A.ASSESSMENT_ITEM_ID, A.AUTH, U.USER_NAME, A.ACTION ";
    $SQL .= " FROM RISK_ASSESSMENT_ACTION A ";
    $SQL .= "  LEFT OUTER JOIN S_SYS_USER_SET U ON A.UNO = U.UNO ";
    $SQL .= " WHERE A.ASSESSMENT_ID = :assessmentId ";
    $SQL .= "  AND A.AUTH IN (SELECT MINOR_CD FROM RISK_CODE_SET WHERE MAJOR_CD = 'AUTH' AND IS_USE = 'Y' AND VAL1 = 'HEAD') ";
    $SQL .= " UNION ";
    $SQL .= " SELECT A.ASSESSMENT_ITEM_ID, A.AUTH, S.COMP_NAME, A.ACTION ";
    $SQL .= " FROM RISK_ASSESSMENT_ACTION A ";
    $SQL .= "  LEFT OUTER JOIN JOB_SUBCON_INFO S ON A.CNO = S.CNO AND S.JNO = :jno ";
    $SQL .= " WHERE A.ASSESSMENT_ID = :assessmentId ";
    $SQL .= "  AND A.AUTH = 'SUBCONTRACTOR' ";
    $SQL .= ") ";
    $SQL .= "PIVOT ( ";
    $SQL .= " MAX(USER_NAME) AS USER_NAME, MAX(ACTION) AS ACTION FOR AUTH IN ('SUBCONTRACTOR' AS SUBCONTRACTOR, 'SUPERVISOR' AS SUPERVISOR, 'SAFETY_MANAGER' AS SAFETY_MANAGER, 'SUPERINTENDENT' AS SUPERINTENDENT) ";
    $SQL .= ") ";
    $SQL .= "), ";
    $SQL .= "T_W AS ( ";
    $SQL .= "SELECT WORK_TYPE_ID, SUBSTR(SYS_CONNECT_BY_PATH(WORK_TYPE_NAME, '>'), INSTR(SYS_CONNECT_BY_PATH(WORK_TYPE_NAME, '>'), '>', 1, 2) + 1) AS WORK_TYPE_PATH ";
    $SQL .= "FROM RISK_WORK_TYPE ";
    $SQL .= "WHERE CONNECT_BY_ISLEAF = 1 ";
    $SQL .= "START WITH PARENT_WORK_TYPE_ID = 0 ";
    $SQL .= "CONNECT BY PRIOR WORK_TYPE_ID = PARENT_WORK_TYPE_ID ";
    $SQL .= ") ";
    $SQL .= "SELECT D.FUNC_NO, F.FUNC_NAME, P.APPROVAL_STATUS, D.ASSESSMENT_ITEM_ID, D.WORK_TYPE_ID, W.WORK_TYPE_PATH, D.LOCATION, D.EQUIPMENT, ";
    $SQL .= " D.RISK_FACTOR, S.COMP_NAME, A.SUBCONTRACTOR_USER_NAME, A.SUBCONTRACTOR_ACTION, A.SUPERVISOR_USER_NAME, A.SUPERVISOR_ACTION, A.SAFETY_MANAGER_USER_NAME, A.SAFETY_MANAGER_ACTION, A.SUPERINTENDENT_USER_NAME, A.SUPERINTENDENT_ACTION, ";
    $SQL .= " D.RISK_TYPE, T.CD_NM AS RISK_TYPE_NAME, D.FREQUENCY, D.STRENGTH, D.IS_CHECK, D.SUBCON_USER_NAME, D.ACTION_DEADLINE, D.UNO, U.USER_NAME AS APPROVER_NAME ";
    $SQL .= "FROM RISK_ASSESSMENT_DETAIL D ";
    $SQL .= " JOIN COMMON.COMM_FUNC_QHSE F ON D.FUNC_NO = F.FUNC_NO ";
    $SQL .= " JOIN RISK_APPROVAL_TARGET P ON D.ASSESSMENT_ID = P.ASSESSMENT_ID AND P.APPROVAL_TYPE = :approvalType AND D.CNO = P.CNO AND D.FUNC_NO = P.FUNC_NO ";
    if(!empty($approvalStatus)) {
        $SQL .= " AND P.APPROVAL_STATUS = :approval_status ";
    }
    $SQL .= " JOIN T_W W ON D.WORK_TYPE_ID = W.WORK_TYPE_ID ";
    $SQL .= " LEFT OUTER JOIN JOB_SUBCON_INFO S ON D.CNO = S.CNO AND S.JNO = :jno ";
    $SQL .= " LEFT OUTER JOIN S_SYS_USER_SET U ON D.UNO = U.UNO ";
    $SQL .= " LEFT OUTER JOIN T_A A ON D.ASSESSMENT_ITEM_ID = A.ASSESSMENT_ITEM_ID ";
    $SQL .= " LEFT OUTER JOIN RISK_CODE_SET T ON T.MAJOR_CD = 'RISK_TYPE' AND D.RISK_TYPE = T.MINOR_CD ";
    $SQL .= "WHERE D.ASSESSMENT_ID = :assessmentId ";
    if(!empty($cno)) {
        $SQL .= " AND D.CNO = :cno ";
    } 
    if(!empty($funcNo)) {
        $SQL .= " AND D.FUNC_NO = :funcNo ";
    }
    if ($auth == "SUPERVISOR") {
        $SQL .= " AND P.FUNC_NO IN (SELECT FUNC_NO FROM JOB_MANAGER_FUNC WHERE JNO = :jno AND UNO = :uno)";
    }
    // $SQL .= "ORDER BY D.CNO, F.SORT_NO, D.WORK_TYPE_ID, D.ASSESSMENT_ITEM_ID ";
    $SQL .= "ORDER BY D.CNO, F.SORT_NO, D.ASSESSMENT_ITEM_ID ";
    $params = array(
        ":assessmentId" => $assessmentId,
        ":jno" => $jno,
        ":approvalType" => 'ASMT_REP', 
        ":cno" => $cno,
        ":funcNo" => $funcNo,
        ":approval_status" => $approvalStatus,
        ":uno" => $user->uno
    );
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        $workTypePath = $row["work_type_path"];

        $subcontractorUserName = $row["subcontractor_user_name"];
        if (empty($subcontractorUserName)) {
            $subcontractorUserName = $row["comp_name"];
        }

        $rating = 0;
        if (!empty($row["frequency"]) && !empty($row["strength"])) {
            $rating = $row["frequency"] * $row["strength"];
        }
        $isDanger = false;
        if ($rating >= $dangerHazardLevel) {
            $isDanger = true;
        }

        $canEdit = false;
        if ($_SESSION["risk"]["auth"] == "SUPERVISOR") {
            if ("10" == $row["approval_status"]) {
                $canEdit = true;
                // $supervisorAction = $row["supervisor_action"];
            }
            // else {
            //     $supervisorAction = nl2br($row["supervisor_action"]);
            // }
            // $safety_managerAction = nl2br($row["safety_manager_action"]);
            // $superintendentAction = nl2br($row["superintendent_action"]);
        }
        else if ($_SESSION["risk"]["auth"] == "SAFETY_MANAGER") {
            // $supervisorAction = nl2br($row["supervisor_action"]);
            if ("20" == $row["approval_status"]) {
                $canEdit = true;
                // $safety_managerAction = $row["safety_manager_action"];
            }
            // else {
            //     $safety_managerAction = nl2br($row["safety_manager_action"]);
            // }
            // $superintendentAction = nl2br($row["superintendent_action"]);
        }
        else if ($_SESSION["risk"]["auth"] == "SUPERINTENDENT") {
            // $supervisorAction = nl2br($row["supervisor_action"]);
            // $safety_managerAction = nl2br($row["safety_manager_action"]);
            if ("30" == $row["approval_status"]) {
                $canEdit = true;
                // $superintendentAction = $row["superintendent_action"];
            }
            // else {
            //     $superintendentAction = nl2br($row["superintendent_action"]);
            // }
        }
    
        $assessmentList[] = array(
            //항목 고유번호
            "assessmentItemId" => $row["assessment_item_id"],
            //공종(현장) 고유번호
            "funcNo" => $row["func_no"], 
            //공종(현장) 배경색
            "funcColor" => $funcColorList[$row["func_no"]],
            //공종(현장)명
            "funcName" => $row["func_name"], 
            //작업단위 고유번호
            "workTypeId" => $row["work_type_id"],
            //작업단위
            "workTypePath" => $workTypePath,
            //장소/위치
            "txtLocation" => $row["location"],
            //사용장비/도구
            "txtEquipment" => $row["equipment"],
            //위험요인
            "txtRiskFactor" => $row["risk_factor"],
            //협력업체명
            "subcontractorUserName" => $subcontractorUserName, 
            //안전보건관리대책 - 협력업체
            "subcontractorAction" => $row["subcontractor_action"], 
            "subcontractorTxtAction" => nl2br($row["subcontractor_action"]), 
            //관리감독자명
            "supervisorUserName" => $row["supervisor_user_name"], 
            //안전보건관리대책 - 관리감독자
            "supervisorAction" => $row["supervisor_action"], 
            "supervisorTxtAction" => nl2br($row["supervisor_action"]), 
            //안전관리자명
            "safety_managerUserName" => $row["safety_manager_user_name"], 
            //안전보건관리대책 - 안전관리자
            "safety_managerAction" => $row["safety_manager_action"], 
            "safety_managerTxtAction" => nl2br($row["safety_manager_action"]), 
            //현장소장명
            "superintendentUserName" => $row["superintendent_user_name"], 
            //안전보건관리대책 - 현장소장
            "superintendentAction" => $row["superintendent_action"], 
            "superintendentTxtAction" => nl2br($row["superintendent_action"]), 
            //재해형태
            "riskType" => $row["risk_type"],
            //재해형태명
            "riskTypeName" => $row["risk_type_name"],
            //빈도
            "frequency" => $row["frequency"],
            //강도
            "strength" => $row["strength"],
            //등급
            "rating" => $rating,
            //위험성
            "isDanger" => $isDanger,
            //회의대상
            "isCheck" => $row["is_check"],
            //조치자
            "subconUserName" => $row["subcon_user_name"], 
            //확인자
            "approverName" => $row["approver_name"], 
            //조치기한
            "actionDeadline" => $row["action_deadline"],
            //편집 가능 여부
            "canEdit" => $canEdit
        );
    }

    return $assessmentList;
}

function getUserList($tempFuncNo = '') {
    global $db;
    global $jno;
    global $funcNo;
    global $assessmentId;

    if(empty($funcNo)) {
        $funcNo = $tempFuncNo;
    }

    $approvalList = array(
        "SUPERVISOR",
        "SAFETY_MANAGER",
        "SUPERINTENDENT"
    );

    //결재자 목록
    $userList = array();
    foreach($approvalList as $auth) {
        $SQL = "WITH T_AU AS (
                                SELECT APPROVER_UNO 
                                FROM RISK_APPROVAL_TARGET T
                                RIGHT OUTER JOIN APPROVAL_INFO A ON A.APPROVAL_TARGET_ID = T.APPROVAL_TARGET_ID
                                WHERE ASSESSMENT_ID = :assessment_id
                                AND T.FUNC_NO = :funcNo
                            ) ";
        $SQL .= "SELECT M.AUTH, M.UNO, U.USER_NAME, M.TEAM_LEADER, ";
        $SQL .= "CASE WHEN M.UNO = :loginUno THEN 1 WHEN A.APPROVER_UNO = M.UNO THEN 2 ELSE 3 END AS LOGIN_USER ";
        $SQL .= "FROM JOB_MANAGER M ";
        $SQL .= " JOIN S_SYS_USER_SET U ON M.UNO = U.UNO ";
        if($auth == "SUPERVISOR") {
            $SQL .= " JOIN JOB_MANAGER_FUNC F ON M.JNO = F.JNO AND M.UNO = F.UNO ";
        }
        $SQL .= "LEFT OUTER JOIN T_AU A ON A.APPROVER_UNO = M.UNO ";
        $SQL .= "WHERE M.JNO = :jno ";
        $SQL .= " AND M.AUTH = :auth ";
        if($auth == "SUPERVISOR") {
            $SQL .= " AND F.FUNC_NO = :funcNo ";
        }
        $SQL .= " ORDER BY LOGIN_USER, TEAM_LEADER DESC";
        $params = array(
            ":jno" => $jno,
            ":funcNo" => $funcNo,
            ":loginUno" => $_SESSION["user"]["uno"],
            ":auth" => $auth,
            ":assessment_id" => $assessmentId
        );
        $db->query($SQL, $params);
        while($db->next_record()) {
            $row = $db->Record;
    
            $userList[strtolower($row["auth"])][] = array(
                "uno" => $row["uno"], 
                "userName" => $row["user_name"] 
            );
        }
    }

    return $userList;
}
?>
