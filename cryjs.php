<!DOCTYPE html>
<html>
<head>
	<title></title>
	<script type="text/javascript" src="aes.js"></script>
	<script type="text/javascript">
	key = CryptoJS.enc.Hex.parse("436574536f667445454d537973576562");
	iv  = CryptoJS.enc.Hex.parse("1934577290ABCDEF1264147890ACAE45");
		function EncryptData(data){
		 var encrypted = CryptoJS.AES.encrypt(data, key, {iv: iv});
		 return encrypted;
		}
		document.write(EncryptData('<?php echo $_GET['k']; ?>'));
	</script>
</head>
<body>
</body>
</html>