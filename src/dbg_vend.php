<?php
$conn = @mysqli_connect('db-bridge','root','inv123','backoffice',3306);
mysqli_query($conn,'SET NAMES latin1');
$r = mysqli_query($conn, "SELECT VndCode, VndName, VndAdd1, VndAdd2, VndAdd3, VndAdd4 FROM gblvend WHERE (VndAdd1 != '' AND VndAdd1 IS NOT NULL) LIMIT 5");
while($row = mysqli_fetch_assoc($r)) {
  echo $row['VndCode'].' | ';
  echo @iconv('TIS-620','UTF-8//IGNORE',$row['VndAdd1']??'').' | ';
  echo @iconv('TIS-620','UTF-8//IGNORE',$row['VndAdd2']??'').' | ';
  echo @iconv('TIS-620','UTF-8//IGNORE',$row['VndAdd3']??'')."\n";
}
