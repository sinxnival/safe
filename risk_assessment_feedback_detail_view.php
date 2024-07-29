<script>
$(document).ready(function() {
    $("#mode").val("INIT");
    $("#assessmentId").val(<?php echo $_POST["assessmentId"]?>);
    $("#assessmentItemId").val(<?php echo $_POST["selAssessItemId"]?>);
    $("#feedbackDate").val('<?php echo $_POST["selFeedbackDate"]?>');
    $("#jno").val('<?php echo $_POST["jno"]?>');

    $.ajax({
        type: "POST",
        url: "risk/risk_assessment_feedback_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            var html = '';
            var assessItemInfo = result["assessmentItemInfo"];

            $("#cno").val(assessItemInfo["cno"]);

            //위험 요인 정보
            html += '<tr>';
            html += '<th>차수</th>';
            html += '<td>'+ assessItemInfo["seq"] +'<td>';
            html += '<th>적용기간</th>';
            html += '<td>'+ assessItemInfo["startDate"] + ' ~ ' + assessItemInfo["endDate"] +'<td>';
            html += '<th>작성일자</th>';
            html += '<td>'+ assessItemInfo["feedbackDate"] +'<td>';
            html += '<th>업체명</th>';
            html += '<td>'+ assessItemInfo["compName"] +'<td>';
            html += '</tr>';

            $("#tblAssessmentInfo tbody").empty().append(html);

            //위험성 평가 안전보건관리대책
            var html = "";
            var list = result["assessmentList"];
            html += '<tr style="background-color: ' + list["funcColor"] + ';">';
            html += '<td>' + list["funcName"] + '</td>';
            html += '<td rowspan="6">' + list["location"] + '</td>';
            html += '<td rowspan="6">' + list["equipment"] + '</td>';
            html += '<td colspan="2">' + list["workTypeName"] + '</td>';
            html += '<td>' + list["riskFactor"] + '</td>';
            html += '<td colspan="3">' + list["riskTypeName"] + '</td>';
            html += '<td rowspan="6">' + list["actionDeadline"] + '</td>';
            html += '<td>' + list["subconUserName"] + '</td>';
            html += '</tr>';
            $.each(result["authList"], function(j, auth) {
                html += '<tr>';
                if (j == 0) {
                    html += '<td rowspan="5"></td>';
                }
                html += '<td>' + auth["name"] + '</td>';
                html += '<td>' + list[auth["code"] + "UserName"] + '</td>';
                html += '<td>' + list[auth["code"] + "Action"] + '</td>';
                if (j == 0) {
                    html += '<td rowspan="5">' + list["frequency"] + '</td>';
                    html += '<td rowspan="5">' + list["strength"] + '</td>';
                    html += '<td rowspan="5"';
                    if (list["isDanger"]) {
                        html += ' class="isDanger"';
                    }
                    html += '>';
                    html += list["rating"] + '</td>';
                    html += '</td>';
                    html += '<td rowspan="5">' + list["approverName"] + '</td>';
                }
                html += '</tr>';
            });
            
            $("#tblFeedbackAssessList tbody").empty().append(html);

            //점검상태
            html = '';
            $(result["inspectionList"]).each(function(i, info) {
                html += '<div class="form-check-inline">';
                html += '<label class="form-check-label">';
                html += '<input type="radio" class="form-check-input" name="inspectionState" value="'+ info["minorCd"] +'">' + info["cdNm"];
                html += '</label>';
                html += '</div>';
            });
            $("#divInspection").append(html);

            //피드백 정보
            var feedbackInfo = result["feedbackInfo"];
            if(feedbackInfo) {
                $("input:radio[name ='inspectionState']").each(function() {
                    if($(this).val() == feedbackInfo["inspectionState"]) {
                        $(this).prop('checked', true);
                    }
                });
                $("#inspectionNote").val(feedbackInfo["inspectionNote"]);
                $("#btnDelFeedback").show();
                //확인 버튼
                var userType = '<?php echo $_SESSION["risk"]["user_type"]?>';
                if(userType == "HEAD") {
                    var jobManager = result["jobManagerList"];
                    var jobManagerList = [];
                    var uno = '<?php echo $user->uno ?>';
                    
                    $(jobManager).each(function(i, info) {
                        jobManagerList.push(info["uno"]);
                        sessionStorage.setItem("chkManager", true);
                    });
    
                    if(jobManagerList.includes(uno)) {
                        $("#btnChkFeedback").show();

                    }
                }
            }
            //신규 작성 
            else {
                $("input:radio[name ='inspectionState']").eq(0).prop('checked', true);
            }

            //이미지 불러오기
            var feedbackImgList = result["feedbackImgList"];
            if(feedbackImgList) {
                html = '';
                var hidden = '';
                $(feedbackImgList).each(function(i, info) {
                    html += '<div class="col-sm-6 img">';
                    html += '<div class="input-group mb-2">';
                    html += '<div class="custom-file">';
                    html += '<label class="custom-file-label" for="customFile">';
                    html += '<a href="common/file_download.php?mKind=FEEDBACK&fno=' + info["feedbackFno"] + '" target="_blank">';
                    html += info["fileName"];
                    html += '</a>';
                    html += '</label>';
                    html += '</div>';
                    html += '<div class="input-group-append">';
                    html += '<button type="button" class="btn btn-secondary existImg" onclick="delAttachedFile(this);">&times;</button>';
                    html += '<input type="hidden" value="' + info["feedbackFno"] + '" />';
                    html += '</div>';
                    html += '</div>';
                    html += '<div>';
                    html += '<img src="'+ info["fileLocation"] +'" style="height:250px" class="winpop-img img-thumbnail img-responsive">';
                    html += '</div>';
                    html += '</div>';
    
                    hidden += '<input type="hidden" name="existAttach[]" value="' + info["fileName"] + '"/>';
                });
                $("#divInspectionImg").append(html);
                $("#hiddenExistAttach").empty().append(hidden);
            }
            //이미지 없을 시 input file 추가 
            else {
                onBtnAddImageClick();
            }
        },
        complete : function() {
            //유효성 검사
            $(".validateElement").each(function() {
                $(this).on("propertychange change keyup input paste", function(event) {
                    // If value has changed...
                    if ($(this).data('oldVal') != $(this).val()) {
                        // Updated stored value
                        $(this).data('oldVal', $(this).val());
                        validateElement(this);
                    }
                });
            });
        },
        error: function(request, status, error) {
            alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
        }
    });

    //저장 버튼
    $("#btnSaveFeedback").on('click', onBtnSaveFeedbackClick);
    //사진 추가 버튼
    $("#btnAddImage").on('click', onBtnAddImageClick);
    //목록 버튼
    $("#btnMoveList").on('click', function() {
        $("<input>").attr({
            type: "hidden",
            id: "page_id",
            name: "page_id",
            value : "risk_assessment_feedback"
        }).appendTo( $("#mainForm") );
        $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
    });
    //삭제 버튼
    $("#btnDelFeedback").on('click', onBtnDelFeedbackClick);
    //확인 버튼
    $("#btnChkFeedback").on('click', onBtnChkFeedbackClick);
});

//유효성 검사
function validateElement(obj) {
    var val = obj.value;
    if(val.trim() == '' || val.trim() == null) {
        $(obj).removeClass("is-valid").addClass("is-invalid");
        $(obj).closest(".form-group").find(".invalid-feedback").html("필수 입력입니다.");
        $(obj).closest(".form-group").find(".invalid-feedback").show();

        return false;
    } else {
        $(obj).removeClass("is-invalid");
        $(obj).closest(".form-group").find(".invalid-feedback").html("");
        $(obj).closest(".form-group").find(".invalid-feedback").hide();

        return true;
    }
}

//저장 버튼
function onBtnSaveFeedbackClick() {
    //유효성 검사
    var validCnt = 0;
    $(".validateElement").each(function() {
        valid = validateElement(this);
        if(valid == false) {
            validCnt++;
        }
    });

    if(validCnt == 0) {
        $("#mode").val("SAVE");

        var formdata = new FormData($("#mainForm")[0]);

        $.ajax({ 
            type: "POST", 
            url: "risk/risk_assessment_feedback_detail.php", 
            data: formdata,
            dataType: "json", 
            contentType: false,
            processData: false,
            success: function(result) {
                if(result["proceed"] == true) {
                    //삭제 아이디 비우기
                    $("#hiddenDeleteAttach").empty();
                    //알림
                    $("#divResultMsg").empty().html(result["msg"]).fadeIn();
                    $("#divResultMsg").delay(5000).fadeOut();
                    //삭제 버튼
                    $("#btnDelFeedback").show();
                    //확인 버튼
                    var chkManager = sessionStorage.getItem("chkManager");
                    if(chkManager) {
                        $("#btnChkFeedback").show();
                    }
                }
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
    }
}

//첨부파일 선택 시
function onAttachFileChange(obj) {
    var fileName = $(obj).val().split("\\").pop();
    $(obj).siblings(".custom-file-label").addClass("selected").html(fileName);

    var reader = new FileReader();
    reader.onload = function(e) {
        $(obj).closest(".img").find("img").attr('src', e.target.result);
    }
    reader.readAsDataURL(obj.files[0]);
}

//첨부파일 삭제
function delAttachedFile(obj) {
    if($(obj).closest(".img").find("input[type='file']").val() == '') {
        $(obj).closest(".img").remove();
    } else {
        if(confirm("삭제하시겠습니까?")) {
            //기존 이미지
            if($(obj).hasClass("existImg")) {
                $(obj).closest(".img").remove();
                var deleteId = $(obj).siblings("input[type='hidden']").val();
                var hidden = '';
                hidden = '<input type="hidden" name="deleteAttachIdList[]" value="' + deleteId + '" />';
                $("#hiddenDeleteAttach").append(hidden);
            }
            //새 이미지 
            else {
                $(obj).closest(".img").find(".custom-file-label").removeClass("selected").html('<i class="fa-solid fa-cloud-arrow-up"></i> 파일을 선택하세요');
                $(obj).closest(".img").find("img").attr('src', 'image/no_image.gif');
                $(obj).closest(".img").find("input[type='file']").val('');
            }
        }
    }
}

//사진 추가 버튼
function onBtnAddImageClick() {
    var html = '';
    html += '<div class="col-sm-6 img">';
    html += '<div class="input-group mb-2">';
    html += '<div class="custom-file">';
    html += '<input type="file" class="custom-file-input" name="newAttachFile[]" onchange="onAttachFileChange(this)" accept="image/*"/>';
    html += '<label class="custom-file-label" for="customFile"><i class="fa-solid fa-cloud-arrow-up"></i> 파일을 선택하세요</label>';
    html += '</div>';
    html += '<div class="input-group-append">';
    html += '<button type="button" class="btn btn-secondary" onclick="delAttachedFile(this);">&times;</button>';
    html += '</div>';
    html += '</div>';
    html += '<div>';
    html += '<img src="image/no_image.gif" style="height:250px" class="winpop-img img-thumbnail img-responsive">';
    html += '</div>';
    html += '</div>';

    $("#divInspectionImg").append(html);
}

//삭제 버튼
function onBtnDelFeedbackClick() {
    if(confirm("피드백정보를 삭제하시겠습니까?")) {
        $("#mode").val("DELETE");
    
        $.ajax({
            type: "POST",
            url: "risk/risk_assessment_feedback_detail.php",
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                alert("삭제되었습니다.");
                //목록으로 이동
                $("<input>").attr({
                    type: "hidden",
                    id: "page_id",
                    name: "page_id",
                    value : "risk_assessment_feedback"
                }).appendTo( $("#mainForm") );
                $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
    }
}

//확인 버튼
function onBtnChkFeedbackClick() {
    $("#mode").val("CHECK");

    $.ajax({
        type: "POST",
        url: "risk/risk_assessment_feedback_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            alert("확인되었습니다.");
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
        });
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
</script>

<div class="menu-sticky-top">
<div>
    <ol class="breadcrumb">
        <li class="breadcrumb-item">안전보건 위험성평가</li> 
        <li class="breadcrumb-item">위험성평가 피드백</li>
    </ol>
</div>
<div class="btnList">
    <span class="btn btn-primary ml-2" id="btnDelFeedback" style="display:none"><i class="fa-solid fa-trash-can"></i>&nbsp;삭제</span>
    <span class="btn btn-primary ml-2" id="btnChkFeedback" style="display:none"><i class="fa-solid fa-check"></i>&nbsp;확인</span>
    <span class="btn btn-primary ml-2" id="btnSaveFeedback"><i class="fa-solid fa-floppy-disk"></i>&nbsp;저장</span>
    <span class="btn btn-primary ml-2" id="btnMoveList"><i class="fa-solid fa-rotate-left"></i>&nbsp;목록</span>
</div>
</div>

<form id="mainForm" name="mainForm" method="post" enctype="multipart/form-data">
<div class="container-fluid">
    <div id="divResultMsg" class="alert alert-primary" style="display: none;"></div>
    <div class="container-xxl p-3 my-3 border">
        <table class="table table-borderless p-3 mb-0 table-title" id="tblAssessmentInfo">
            <tbody></tbody>
        </table>
    </div>
    <table id="tblFeedbackAssessList" class="table table-bordered table-sm">
        <thead class="thead-light">
            <tr>
                <th rowspan="2" style="width: 100px;">공종(현장)명</th>
                <th rowspan="2" style="width: 150px;">장소/위치</th>
                <th rowspan="2" style="width: 150px;">사용장비/도구</th>
                <th colspan="2" style="width: 300px;">작업단위</th>
                <th>위험요인</th>
                <th colspan="3" colspan="4" style="width: 150px;">재해형태</th>
                <th rowspan="2" style="width: 120px;">조치기한</th>
                <th style="width: 100px;">조치자</th>
            </tr>
            <tr>
                <th style="width: 150px;">담당</th>
                <th style="width: 150px;">작성/검토</th>
                <th>안전보건관리대책</th>
                <th style="width:  50px;">빈도</th>
                <th style="width:  50px;">강도</th>
                <th style="width:  50px;">등급</th>
                <th>확인자</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <div class="row" id="divInspectionImg">
        <!-- 점검상태 -->
        <div class="col-sm-3" id="divInspection">
            <div style="border-bottom: 1px solid #E0E0E0;">
                <h6><b>점검상태</b></h6>
            </div>
        </div>
        <!-- 점검의견 -->
        <div class="col-sm-9 form-group">
            <div style="border-bottom: 1px solid #E0E0E0;">
                <h6><b>점검의견</b></h6>
            </div>
            <textarea class="form-control validateElement autoheight" id="inspectionNote" name="inspectionNote"></textarea>
            <div class="invalid-feedback"></div>
        </div>
        <!-- 관련사진 -->
        <div class="col-sm-12 legend"><b>관련사진</b><span class="btn btn-warning btn-sm ml-2" id="btnAddImage"><i class="fa-solid fa-plus"></i></span></div>
    </div>
</div>

<div id="hiddenDeleteAttach"></div>
<div id="hiddenExistAttach"></div>
<input type="hidden" id="mode" name="mode" />
<input type="hidden" id="assessmentId" name="assessmentId" />
<input type="hidden" id="assessmentItemId" name="assessmentItemId" />
<input type="hidden" id="feedbackDate" name="feedbackDate" />
<input type="hidden" id="cno" name="cno" />
<input type="hidden" id="jno" name="jno" />
</form>
