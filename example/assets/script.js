let form = document.forms.namedItem("file-compression");
form.addEventListener('submit', function (e) {
  
  let oOutput = document.querySelector("div");
  let oData = new FormData(this);
  
  let oReq = new XMLHttpRequest();
  oReq.open("POST", "compress.php", true);
  oReq.onload = function () {
    if (oReq.status === 200) {
      oOutput.innerHTML = "Uploaded!";
    } else {
      oOutput.innerHTML = "Error " + oReq.status + " occurred when trying to upload your file.<br \/>";
    }
  };
  
  oReq.send(oData);
  e.preventDefault();
}, false);
