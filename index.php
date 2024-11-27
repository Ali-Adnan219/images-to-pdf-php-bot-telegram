<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('BOT_TOKEN', '');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
//echo file_get_contents("https://api.telegram.org/bot" . BOT_TOKEN. "/setwebhook?url=" . $_SERVER['SERVER_NAME'] . "" . $_SERVER['SCRIPT_NAME']);

// دالة عامة للتواصل مع بوت Telegram
function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res);
    }
}

// مسار المجلد الرئيسي لتخزين مجلدات المستخدمين
$mainFolder = __DIR__ . '/user_images/';
$libraryPath = __DIR__ . '/lib/fpdf.php';
require_once($libraryPath);
if (!file_exists($mainFolder)) {
    mkdir($mainFolder, 0777, true);
}

//////////////////////
$update = json_decode(file_get_contents('php://input'));
$updata_id=$update->update_id;
@$message = $update->message;
@$from_id = $message->from->id;
@$chat_id = $message->chat->id;
@$sticker = $message->sticker->file_id;
@$message_id = $message->message_id;
@$first_name = $message->from->first_name;
@$last_name = $message->from->last_name;
@$username = $message->from->username;
@$text  = $message->text;
@$firstname = $update->callback_query->from->first_name;
@$usernames = $update->callback_query->from->username;
@$chatid = $update->callback_query->message->chat->id;
@$fromid = $update->callback_query->from->id;
@$membercall = $update->callback_query->id;
@$reply = $update->message->reply_to_message->forward_from->id;

@$data = $update->callback_query->data;
$text_callback_query=$update->callback_query->message->text;
$chat_id_reply_callback_query=$update->callback_query->message->reply_to_message->chat->id;
$msgid_reply=$update->callback_query->message->reply_to_message->message_id;
@$messageid = $update->callback_query->message->message_id;
@$tc = $update->message->chat->type;
@$gpname = $update->callback_query->message->chat->title;
@$namegroup = $update->message->chat->title;

@$newchatmemberid = $update->message->new_chat_member->id;
@$newchatmemberu = $update->message->new_chat_member->username;
@$rt = $update->message->reply_to_message;
@$replyid = $update->message->reply_to_message->message_id;
@$tedadmsg = $update->message->message_id;

@$re_id = $update->message->reply_to_message->from->id;
@$re_user = $update->message->reply_to_message->from->username;
@$re_name = $update->message->reply_to_message->from->first_name;
@$re_msgid = $update->message->reply_to_message->message_id;
@$re_chatid = $update->message->reply_to_message->chat->id;
@$message_edit_id = $update->edited_message->message_id;
@$chat_edit_id = $update->edited_message->chat->id;
$photo = $message->photo;


// الدالة للتعامل مع الصور المستلمة
function handleImage($file_id, $userFolder) {
    $filePath = API_URL . "getFile?file_id=" . $file_id;
    $fileData = json_decode(file_get_contents($filePath), true);

    if (!isset($fileData['result']['file_path'])) return false;

    $fileURL = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $fileData['result']['file_path'];
    $fileExtension = pathinfo($fileData['result']['file_path'], PATHINFO_EXTENSION);

    // الحصول على اسم جديد حسب التسلسل
    $newImageName = count(glob($userFolder . "/*")) + 1 . '.' . $fileExtension;

    // تنزيل الصورة
    file_put_contents($userFolder . '/' . $newImageName, file_get_contents($fileURL));
}

// الدالة لتحويل الصور إلى PDF
function imagesToPDF($userFolder, $chat_id, $fileName = 'output.pdf', $description = 'ملف PDF المحول') {
    $pdf = new FPDF();
    $images = glob($userFolder . "/*.{jpg,jpeg,png}", GLOB_BRACE);
    sort($images); // ترتيب الصور

    foreach ($images as $image) {
        list($width, $height) = getimagesize($image);
        $width_mm = $width * 0.264583;
        $height_mm = $height * 0.264583;
        $pdf->AddPage($width > $height ? 'L' : 'P', array($width_mm, $height_mm));
        $pdf->Image($image, 0, 0, $width_mm, $height_mm);
    }

    $pdfPath = $userFolder . '/' . $fileName;
    $pdf->Output($pdfPath, 'F');

    // إرسال ملف PDF للمستخدم
    sendPDF($chat_id, $pdfPath, $fileName, $description);
}

// دالة لإرسال ملف PDF
function sendPDF($chat_id, $filePath, $fileName = 'document.pdf', $description = 'ملف PDF المحول') {
    bot("sendDocument", [
        'chat_id' => $chat_id,
        'document' => new CURLFile(realpath($filePath)),
        'caption' => $description,
        'filename' => $fileName
    ]);
}

// التعامل مع الرسالة المستلمة
if (isset($message)) {
    $userFolder = $mainFolder . $chat_id;

    if (!file_exists($userFolder)) {
        mkdir($userFolder, 0777, true);
    }

    if (isset($photo)) {
        // التعامل مع الصور المستلمة
        $file_id = end($photo)->file_id;
        handleImage($file_id, $userFolder);

        bot("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "تم استلام الصورة! لديك الآن " . count(glob($userFolder . "/*.{jpg,jpeg,png}", GLOB_BRACE)) . " صور. يمكنك تحويلها إلى PDF أو مسح جميع الصور.",
            'reply_markup' => json_encode(['keyboard' => [['تحويل إلى PDF'], ['مسح الصور']], 'resize_keyboard' => true]),
            'reply_to_message_id' => $message_id
        ]);

    } elseif (isset($text)) {
        if ($text == "تحويل إلى PDF") {
            $images = glob($userFolder . "/*.{jpg,jpeg,png}", GLOB_BRACE);
            if (count($images) > 0) {
                imagesToPDF($userFolder, $chat_id, '@Bot_S7.pdf', "شارك البوت و فيد الطلاب");  // تغيير اسم الملف عند التحويل
                clearImages($userFolder);
            } else {
                bot("sendMessage", [
                    'chat_id' => $chat_id,
                    'text' => "لم يتم العثور على صور لتحويلها."
                ]);
            }
        } elseif ($text == "مسح الصور") {
            clearImages($userFolder);
            bot("sendMessage", [
                'chat_id' => $chat_id,
                'text' => "تم مسح جميع الصور."
            ]);
        } elseif ($text == "/start") {

 bot("sendMessage", [
                'chat_id' => $chat_id,
                'text' => "
❤️ اهلا بك    $first_name في بوت تحويل الصور إلى ملف PDF
"
            ]);
}// start
    }
}


// الدالة لمسح الصور
function clearImages($userFolder) {
    array_map('unlink', glob($userFolder . "/*"));
}


//////////////


function deleteEmptyFolders($dir) {
    // التحقق مما إذا كان المجلد موجودًا
    if (!is_dir($dir)) {
        echo "المجلد غير موجود!";
        return;
    }

    // قراءة محتويات المجلد
    $folders = scandir($dir);

    // التكرار على المجلدات الداخلية
    foreach ($folders as $folder) {
        // تجاهل المجلدات "." و ".."
        if ($folder == '.' || $folder == '..') {
            continue;
        }

        // بناء المسار الكامل للمجلد
        $folderPath = $dir . DIRECTORY_SEPARATOR . $folder;

        // التحقق إذا كان العنصر مجلد
        if (is_dir($folderPath)) {
            // إذا كان المجلد فارغًا، حذفه
            if (count(scandir($folderPath)) == 2) { // المجلد يحتوي فقط على "." و ".."
                rmdir($folderPath);
                echo "تم حذف المجلد الفارغ: $folderPath\n";
            }
        }
    }
}

deleteEmptyFolders($mainFolder);

?>
