<style>
    .table th, td {
        vertical-align: middle !important;
    }
    #tblRiskAssessmentList td::not(.notAlign) {
        text-align:center;
    }
    .banner img {
        width: 190px;
        height: 50px;
    }
    #tblJobManagerInfo th, #tblRiskAssessmentList th {
        background-color:aliceblue;
    }
</style>
<script>
$(document).ready(function() {
   $("#mode").val("INIT");
   if(sessionStorage.getItem("jno")) {
        $("#jno").val(sessionStorage.getItem("jno"));
        $("#divNone").hide();
        $("#divMainPage").show();
    }
    else {
        $("#divNone").show();
        $("#divMainPage").hide();
    }
    <?php if ("SUB" == $_SESSION["risk"]["user_type"]) { ?>
        $(".head_tray").closest("div.d-flex").addClass('display-none');
    <?php } ?>

   if($("#jno").val()) {
       //결재함
        $.ajax({ 
            type: "POST", 
            url: "approval/approval_cnt_data.php", 
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                //결재 갯수
                $.each(result["appMenuCntList"], function(key, val) {
                    $("#div_" + key).find("span").text(val);
                });
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
    
        //수시평가 현황
        $.ajax({
            type: "POST", 
            url: "common/main.php", 
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                //JOB 정보
                var jobInfo = result["jobInfo"];
                $("#tdJobName").text(jobInfo["jobName"]);
                $("#tdJobSd").text(jobInfo["jobSd"]);
                $("#tdJobEd").text(jobInfo["jobEd"]);
                $("#tdJobPm").text(jobInfo["jobPmName"]);
                $("#tdJobPe").text(jobInfo["jobPeName"]);
                $("#tdCompName").text(jobInfo["compName"]);
                $("#tdOrderCompName").text(jobInfo["orderCompName"]);

                //공지사항
                var html = "";
                if (!$.isEmptyObject(result["noticeList"])) {
                    $.each(result["noticeList"], function(i, info) {
                        html += '<tr>';
                        html += '<td class="pl-3">';
                        html += '<a href="javascript:void(0);" onclick="onBtnDetailNoticeClick(' + info["boardNo"] + ')" >';
                        html += info["title"];
                        html += '</a>';
                        html += '</td>';
                        html += '<td class="text-center">';
                        html += info["userName"];
                        html += '</td>';
                        html += '<td class="text-center">';
                        html += info["regDate"];
                        html += '</td>';
                        html += '</tr>';
                    });
                    $("#tblNoticeList tbody").empty().append(html);
                }

                //알림
                html = '';
                if (!$.isEmptyObject(result["alarmList"])) {
                    $.each(result["alarmList"], function(i, info) {
                        html += '<div class="alert alert-info py-1">';
                        if (info["alarmType"] == "ASMT") {
                            html += '<a href="javascript:void(0);" onclick="onMoveDetailAssessmentClick(\'' + info["assessmentType"] + '\', ' + info["assessmentId"] + ', ' + info["funcNo"] + ')">';
                        }
                        else if (info["alarmType"] == "FEEDBACK") {
                            html += '<a href="javascript:void(0);" onclick="onMoveFeedbackAssessmentClick(' + info["assessmentId"] + ', ' + info["assessmentItemId"] + ', \'' + info["feedbackDate"] + '\')">';
                        }
                        html += info["msg"];
                        html += '</a>';
                        html += '</div>';
                    });
                }
                $("#divAlarmList").append(html);

                //수시평가 현황
                html = '';
                if(result["riskAssessmentState"]) {
                    var riskAssessmentState = result["riskAssessmentState"];
                    html = '<i class="fa-solid fa-tv"></i> 위험성평가 수시평가현황 : ' + riskAssessmentState[0]["assess_seq"] + '차 (\'' + riskAssessmentState[0]["startDate"] + ' ~ \'' + riskAssessmentState[0]["endDate"] + ')'
                } else {
                    html += '<i class="fa-solid fa-tv"></i> 위험성평가 수시평가';
                }
                $("#riskAssessmentSeq").html(html);
    
                html = '';
                $(riskAssessmentState).each(function(i, info) {
                    html += '<tr>';
                    html += '<td class="rowspanCompany notAlign pl-3">'+ info["compName"] +'</td>';
                    html += '<td class="text-center">' + info["funcName"] + '</td>';
                    html += '<td class="text-center">'+ info["submitDate"] +'</td>';
                    html += '<td class="text-center">'+ info["supervisorModDate"] +'</td>';
                    html += '<td class="text-center">'+ info["safetyManagerModDate"] +'</td>';
                    html += '<td class="text-center">'+ info["superintendentModDate"] +'</td>';
                    html += '<td class="text-center">'+ info["feedbackDate"] +'</td>';
                    html += '</tr>';
                });
    
                $("#tblRiskAssessmentList tbody").empty().append(html);

                //같은 회사 행 병합
                $(".rowspanCompany").each(function() {
                    var rows = $(".rowspanCompany:contains('" + $(this).text() + "')");
                    if(rows.length > 1) {
                        rows.eq(0).attr("rowspan", rows.length);
                        rows.not(":eq(0)").remove();
                    }
                });

                //담당자 연락처
                html = '';
                $(result["jobManagerInfo"]).each(function(i, info) {
                    //관리감독자
                    if(info["auth"] == "SUPERVISOR") {
                        html += '<tr>';
                        html += '<td class="pl-3">';
                        html += info["userName"];
                        html += '</td>';
                        html += '<td class="text-center">';
                        html += info["cell"];
                        html += '</td>';
                        html += '<td>';
                        html += info["funcName"];
                        html += '</td>';
                        html += '</tr>';
                    }
                });

                $("#supervisorCell").append(html);

                html = '';
                $(result["jobManagerInfo"]).each(function(i, info) {
                    //안전관리자
                    if(info["auth"] == "SAFETY_MANAGER") {
                        html += '<tr>';
                        html += '<td class="pl-3">';
                        html += info["userName"];
                        html += '</td>';
                        html += '<td class="text-center">';
                        html += info["cell"];
                        html += '</td>';
                        html += '<td>';
                        html += '</td>';
                        html += '</tr>';
                    }
                });

                $("#safetyManagerCell").append(html);
            },
            complete: function() {
                importWheather();
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
   }
});

//공지사항
function onMoveNoticeClick() {
    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : "notice"
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}

//프로젝트 정보
function onMoveJobClick() {
    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : "project_detail"
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}

//위험성평가 편집 화면으로 이동
function onMoveDetailAssessmentClick(assessmentType, assessmentId, funcNo) {
    $("<input>").attr({
        type: "hidden",
        id: "assessmentId",
        name: "assessmentId",
        value : assessmentId
    }).appendTo( $("#mainForm") );

    $("<input>").attr({
        type: "hidden",
        id: "funcNo",
        name: "funcNo",
        value : funcNo
    }).appendTo( $("#mainForm") );

    var page_id = "", prePageId = "";
    if (assessmentType == "ASMT_FIR" || assessmentType == "ASMT_REG" ) {
        page_id = "risk_assessment_regular_edit";
        prePageId = "risk_assessment_regular";
    }
    else if (assessmentType == "ASMT_REP") {
        page_id = "risk_assessment_repeated_edit";
        prePageId = "risk_assessment_repeated";
    }
    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : page_id
    }).appendTo( $("#mainForm") );
    $("<input>").attr({
        type: "hidden", 
        id: "prePageId",
        name: "prePageId",
        value : prePageId
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}

//위험성평가 피드백 화면으로 이동
function onMoveFeedbackAssessmentClick(assessmentId, assessmentItemId, feedbackDate) {
    $("<input>").attr({
        type: "hidden",
        id: "assessmentId",
        name: "assessmentId",
        value : assessmentId
    }).appendTo( $("#mainForm") );

    $("<input>").attr({
        type: "hidden",
        id: "selAssessItemId",
        name: "selAssessItemId",
        value : assessmentItemId
    }).appendTo( $("#mainForm") );

    $("<input>").attr({
        type: "hidden",
        id: "selFeedbackDate",
        name: "selFeedbackDate",
        value : feedbackDate
    }).appendTo( $("#mainForm") );

    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : "risk_assessment_feedback_detail"
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}

//전자결재현황
function onMoveApprovalPageClick(tray) {
    if (tray != "") {
        $("<input>").attr({
            type: "hidden",
            id: "tray",
            name: "tray",
            value : tray
        }).appendTo( $("#mainForm") );
    }

    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : "approval"
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}

//위험성 수시평가
function onMoveRiskAssessmentClick() {
    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : "risk_assessment_repeated"
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}

//JOB 선택
function jobSelected() {
    //메인 화면으로 이동
    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : "main"
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}

//날씨 가져오기
function importWheather() {
    $("#mode").val("WEATHER");

    $.ajax({
        type: "POST", 
        url: "common/main.php", 
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            //날씨
            var html = '';
            for(var date in result["forecast"]) {
                year = date.substr(0, 4);
                month = date.substr(4, 2);
                day = date.substr(6, 2);
                var fcstDate = year + '-' + month + '-' + day;

                html += '<tr>';
                html += '<td class="text-center">';
                html += fcstDate;
                html += '</td>';
                html += '<td class="text-center">';
                html += result["forecast"][date]["TMN"] + "/" + result["forecast"][date]["TMX"];
                html += '</td>';
                html += '<td class="text-center">';
                html += result["forecast"][date]["WSD"] + "m/s";
                html += '</td>';
                html += '<td class="text-center">';
                html += result["forecast"][date]["SKY"];
                html += '</td>';
                html += '</tr>';
            }

            $("#tblForecast tbody").append(html);
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}
</script>
<div class="container-fluid">
<br />
<div id="divNone">
    선택된 JOB이 없습니다.
</div>
<div id="divMainPage">
<form id="mainForm" name="mainForm">
<div class="row">
    <div class="col-sm-4">
        <h5><a href="javascript:void(0);" onclick="onMoveJobClick()" class="h5"><i class="fa-solid fa-list"></i> JOB 정보</a></h5>
        <table class="table table-bordered table-sm">
            <colgroup>
                <col style="width: 15%" />
                <col style="width: 35%" />
                <col style="width: 15%" />
                <col style="width: 35%" />
            </colgroup>
            <tbody>
                <tr>
                    <th>JOB 명</th>
                    <td colspan="3" id="tdJobName" class="pl-3"></td>
                </tr>
                <tr>
                    <th>시작일</th>
                    <td id="tdJobSd" style="text-align:center"></td>
                    <th>종료일</th>
                    <td id="tdJobEd" style="text-align:center"></td>
                </tr>
                <tr>
                    <th>PM</th>
                    <td id="tdJobPm" style="text-align:center"></td>
                    <th>PE</th>
                    <td id="tdJobPe" style="text-align:center"></td>
                </tr>
                <tr>
                    <th>End-User</th>
                    <td id="tdCompName" style="text-align:center"></td>
                    <th>Client</th>
                    <td id="tdOrderCompName" style="text-align:center"></td>
                </tr>
            </tbody>
        </table>
        <br /><br />
    </div>
    <div class="col-sm-5">
        <h5><a href="javascript:void(0);" onclick="onMoveNoticeClick()" class="h5"><i class="fa-regular fa-clipboard"></i> 공지사항</a></h5>
        <table class="table table-bordered table-sm" id="tblNoticeList">
            <colgroup>
                <col style="width: 60%" />
                <col style="width: 18%" />
                <col style="width: 22%" />
            </colgroup>
            <thead>
                <tr>
                    <th>제목</th>
                    <th>등록자</th>
                    <th>등록일</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="divAlarmList"></div>
    </div>
    <div class="col-sm-3">
        <h5><i class="fa-regular fa-clipboard"></i> 날씨</h5>
        <div class="container border p-2">
            <table class="table table-borderless table-sm" id="tblForecast">
                <thead>
                    <tr>
                        <th>날짜</th>
                        <th>기온(최저/최고)</th>
                        <th>풍속</th>
                        <th>상태</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-2">
        <h5><a href="javascript: void(0);" onclick="onMoveApprovalPageClick('')" class="h5"><i class="fa-solid fa-briefcase"></i> 전자결재현황</a></h5>
        <div class="border">
            <div class="d-flex" style="cursor: pointer;" onclick="onMoveApprovalPageClick('intray')">
                <div class="p-2"><i class="fa-solid fa-box-archive"></i> 미결함</div>
                <div id="div_intray" class="p-2 ml-auto head_tray"><span class="badge badge-rectangle badge-danger">0</span></div>
            </div>
            <div class="d-flex" style="cursor: pointer;" onclick="onMoveApprovalPageClick('outtray')">
                <div class="p-2"><i class="fa-solid fa-box-archive"></i> 기결함</div>
                <div id="div_outtray" class="p-2 ml-auto head_tray"><span class="badge badge-rectangle badge-secondary">0</span></div>
            </div>
            <div class="d-flex" style="cursor: pointer;" onclick="onMoveApprovalPageClick('mytray')">
                <div class="p-2"><i class="fa-solid fa-box-archive"></i> 상신함</div>
                <div id="div_mytray" class="p-2 ml-auto"><span class="badge badge-rectangle badge-info">0</span></div>
            </div>
            <div class="d-flex" style="cursor: pointer;" onclick="onMoveApprovalPageClick('extray')">
                <div class="p-2"><i class="fa-solid fa-box-archive"></i> 반려함</div>
                <div id="div_extray" class="p-2 ml-auto"><span class="badge badge-rectangle badge-info">0</span></div>
            </div>
            <div class="d-flex" style="cursor: pointer;" onclick="onMoveApprovalPageClick('mantray')">
                <div class="p-2"><i class="fa-solid fa-box-archive"></i> 완료함</div>
                <div id="div_mantray" class="p-2 ml-auto"><span class="badge badge-rectangle badge-secondary">0</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-7">
        <h5><a id="riskAssessmentSeq" href="javascript:void(0);" onclick="onMoveRiskAssessmentClick()" class="h5"></a></h5>
        <table class="table table-bordered table-sm" id="tblRiskAssessmentList">
            <thead>
                <tr>
                    <th rowspan="2">협력업체명</th>
                    <th rowspan="2">공종</th>
                    <th colspan="5">평가현황</th>
                </tr>
                <tr>
                    <th>협력업체</th>
                    <th>관리감독자</th>
                    <th>안전관리자</th>
                    <th>현장소장</th>
                    <th>FeedBack</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <br /><br />
    </div>
    <div class="col-sm-3">
        <h5><a href="javascript:void(0);" onclick="onMoveJobClick()" class="h5"><i class="fa-regular fa-clipboard"></i> 담당자</a></h5>
        <!-- <div class="container border p-2"> -->
            <table class="table table-bordered table-sm" id="tblJobManagerInfo">
                <thead>
                    <tr>
                        <th colspan="3">관리감독자</th>
                    </tr>
                </thead>
                <tbody id="supervisorCell"></tbody>
                <thead>
                    <tr>
                        <th colspan="3">안전관리자</th>
                    </tr>
                </thead>
                <tbody id="safetyManagerCell"></tbody>
            </table>
        <!-- </div> -->
    </div>
</div>

<!-- <div>
    <h5><a href="javascript:void(0);" onclick="onMenuClick('unfitness')"><i class="fa-solid fa-tv"></i> 부적합/시정조치 보고서</a></h5>
    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>순번</th>
                <th>제목</th>
                <th>부적합유형</th>
                <th>예상재해유형</th>
                <th>발생일</th>
                <th>조치기한</th>
                <th>첨부여부</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="7">조회된 데이터가 없습니다.</td>
            </tr>
        </tbody>
    </table>
</div> -->

<?php 
//공지 상세
require_once 'information/notice_detail_view.php';
?>

<input type="hidden" id="jno" name="jno" />
<input type="hidden" id="mode" name="mode" />
<input type="hidden" id="boardNo" name="boardNo" />
</form>
</div>

</div>
