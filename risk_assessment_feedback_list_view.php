<style>
    .feedbackState{
        font-size:small;
        cursor:pointer;
    }
</style>
<script>
$(document).ready(function() {
    $("#mode").val("INIT");
    //jno
    if(sessionStorage.getItem("jno")) {
        $("#jno").val(sessionStorage.getItem("jno"));
    }
    
    //사용자 유형
    $("#userType").val("<?php echo $_SESSION["risk"]["user_type"]?>");

    if($("#userType").val() == "SUB") {
        $("#cno").val(<?php echo $_SESSION["risk"]["cno"]?>);
        $("#btnDownloadFeedback").hide();
    } else {
        $("#btnDownloadFeedback").show();
    }

    //선택된 잡이 있을 시
    if($("#jno").val()) {
        $.ajax({
            type: "POST",
            url: "risk/risk_assessment_feedback_list.php",
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                var html = '';
                $(result["assessmentSeqList"]).each(function(i, info) {
                    html += '<option value="'+ info["assessmentId"] +'" ';
                    if(info["isSelected"] == "Y") {
                        html += 'selected';
                    }
                    html += '>';
                    html += info["seqOption"];
                    html += '</option>';
                });
    
                $("#selAssessSeq").append(html);
    
                showFeedbackList();
            },
            error: function(request, status, error) {
                alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
            }
        });
    }

    //차수 변경시
    $("#selAssessSeq").on('change', showFeedbackList);
    //목록 내보내기
    $("#btnDownloadFeedback").on('click', onBtnDownloadFeedbackClick);
});

//표그리기
function showFeedbackList() {
    $("#mode").val("LIST");
    var assessmentId = $("#selAssessSeq").val();

    if(assessmentId) {
        $("#assessmentId").val(assessmentId);
        $.ajax({
            type: "POST",
            url: "risk/risk_assessment_feedback_list.php",
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                //날짜 배열 만들기
                var seqOption = $("#selAssessSeq option:selected").text();
                var seqDate = seqOption.split(' ');
                var stDate = seqDate[2];
                var endDate = seqDate[4];
                stDate = new Date(stDate);
                endDate = new Date(endDate);
                var diffTime = endDate.getTime() - stDate.getTime();
                var diffDay = diffTime / (1000*60*60*24);
    
                var conDate = [];
                for(var i=0; i <= diffDay; i++) {
                    var nextDate = new Date(
                        stDate.getFullYear(),
                        stDate.getMonth(),
                        stDate.getDate() + i
                    );
                    var year = nextDate.getFullYear();
                    var month = nextDate.getMonth() + 1;
                    var day = nextDate.getDate();
                    var dateFormat = year + '/' + month + '/' + day;
                    dateFormat = dateFormat.substr(2);
                    conDate.push(dateFormat);
                }
    
                //thead
                var html = '';
                html += '<tr>';
                html += '<th style="width:10%">업체명</th>';
                html += '<th style="width:30%">위험요인</th>';
                html += '<th style="width:5%">조치자</th>';
                html += '<th style="width:5%">확인자</th>';
                $(conDate).each(function(i, date) {
                    html += '<th style="width:2.5%">' + date + '</th>';
                });
                html += '</tr>';
    
                $("#tblFeedbackList thead").empty().append(html);
    
                //tbody
                var html = '';
                $(result["feedbackFactorList"]).each(function(i, info) {
                    html += '<tr>';
                    html += '<td>'+ info["compName"] +'</td>';
                    html += '<td>'+ info["riskFactor"] +'</td>';
                    html += '<td>'+ info["subconUserName"] +'</td>';
                    html += '<td>'+ info["userName"] +'</td>';
                    $(conDate).each(function(i, date) {
                        html += '<td id="'+ info["assessmentItemId"] + '_' + date +'" class="feedbackState"></td>';
                    });
                    html += '</tr>';
                });
    
                $("#tblFeedbackList tbody").empty().append(html);
    
                //점검상태
                $(result["feedbackState"]).each(function(i, info) {
                    $(".feedbackState").each(function() {
                        var tdId = $(this).attr('id');
                        var itemDate = info["assessmentItemId"] + '_' + info["feedbackDate"];
                        if(tdId == itemDate) {
                            var inspectionText = info["inspectionState"];
                            if(info["hasFile"]) {
                                inspectionText += ' <i class="fa-solid fa-paperclip"></i>';
                            }
                            $(this).html(inspectionText);
                            if(info["chkUno"]) {
                                $(this).css("background-color", "#FFFFCC");
                            }
                        }
                    });
                });
            },
            complete: function() {
                $(".feedbackState").on('click', function() {
                    var feedbackId = $(this).attr('id');
                    feedbackId = feedbackId.split('_');
                    var assessmentItem = feedbackId[0];
                    var feedbackDate = feedbackId[1];
    
                    $("#selAssessItemId").val(assessmentItem);
                    $("#selFeedbackDate").val(feedbackDate);
    
                    //상세 화면으로 이동
                    $("<input>").attr({
                        type: "hidden",
                        id: "page_id",
                        name: "page_id",
                        value : "risk_assessment_feedback_detail"
                    }).appendTo( $("#mainForm") );
                    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
                });
            },
            error: function(request, status, error) {
                alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
            }
        });
    }
}

//JOB 선택
function jobSelected() {
    //위험성평가 피드백 목록 화면으로 이동
    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : "risk_assessment_feedback"
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}

//목록 내보내기
function onBtnDownloadFeedbackClick() {
    window.location.href = "risk/risk_assessment_feedback_list_download_excel.php?assessmentId=" + $("#assessmentId").val();
}
</script>
<form id="mainForm" name="mainForm" method="post">
<div class="menu-sticky-top">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">안전보건 위험성평가</li>
        <li class="breadcrumb-item">위험성평가 피드백</li>
    </ol>
    <div class="btnList">
        <span class="btn btn-primary ml-2" id="btnDownloadFeedback" style="display:none"><i class="fa-solid fa-file-excel"></i>&nbsp;목록 다운로드</span>
    </div>
</div>
<div class="container-fluid">
    <div class="container-xxl p-3 my-3 border">
        <label class="control-label pull-left" for="selAssessSeq"><b>차수</b></label>
        <select id="selAssessSeq" name="selAssessSeq">
        </select>
    </div>
    <!-- 1.2 피드백 리스트 -->
    <table class="table table-bordered" id="tblFeedbackList">
        <thead>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<input type="hidden" id="mode" name="mode" />
<input type="hidden" id="jno" name="jno" />
<input type="hidden" id="cno" name="cno" />
<input type="hidden" id="assessmentId" name="assessmentId" />
<input type="hidden" id="selAssessItemId" name="selAssessItemId" />
<input type="hidden" id="selFeedbackDate" name="selFeedbackDate" />
<input type="hidden" id="userType" name="userType" />
</form>
