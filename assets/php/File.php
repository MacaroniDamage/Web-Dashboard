<?php

//If you are curious about how this work, you can find the documentation to this class on my GitHub page
//https://github.com/MacaroniDamage/macaronipanel-development/blob/master/Fileusage.md
class File
{
    /**
     * @var PDO
     * Contains the a database handler to access the database
     */
    private $dbh;
    /**
     * @var string
     * Contains the id from the database
     */
    private $fileID;
    /**
     * @var array
     * Contains the tags from the database
     */
    private $tags = array();

    /**
     * @var string
     * Contains the filename of the file. This variable will be written inside the constructor.
     */
    private $fileName;

    /**
     * @var string
     * Contains the concatenated filepath from the root of the webbrowser to the file.
     */
    private $dir;

    /**
     * @var string
     * Contains the absolute path to the file.
     * This is string is used as an unique identifier inside the database
     */
    private $absolutePath;
    /**
     * @var string
     * Containes the relative path to the file.
     * Can be used to embed a link inside a "href" or "src" attribute
     */
    private $relativePath;
    /**
     * @var string
     * Contains the description of the file form the database
     */
    private $description;

    /**
     * @var array
     * Contains the userIDs that can see this file
     */
    private $userIDs = array();

    /**
     * File constructor.
     * @param $dir
     * @param $filename
     * $dir -> Contains the relative path to the file
     * $filename -> Contains the name of the file
     */
    public function __construct($dir, $filename)
    {
        $this->dbh = Config::dbCon();
        $this->fileName = $filename;
        $this->dir = Config::getFolder() . $dir;
        $this->absolutePath = $this->evaluateAbsolutePath();
        $this->relativePath = $this->evaluateRelativePath();
        if ($this->fileExistsInDatabase()) {
            $this->reloadingData();
            $this->userIDs = $this->fetchUserRelationsToFileID();
        }
    }

    /**
     * Takes the data from the database and puts the data into the object
     */
    private function reloadingData(){
        $fileData = $this->fetchFileDataFormDatabase();
        $this->description = $fileData["description"];
        $this->tags = $this->decodeTags($fileData["tags"]);
        $this->fileID = $fileData["id"];

    }
    /**
     * @return bool
     * Checks whether a file exists on the hard disk.
     */
    public function fileExistsInDir()
    {
        return file_exists($this->absolutePath);
    }

    /**
     * @return bool|null
     * true -> Files was added to the database
     * false -> File wasn't added to the database
     * Can be used to determine whether a file should be added to the database
     */
    public function fileExistsInDatabase()
    {
        try {
            $stmt = $this->dbh->prepare("SELECT * FROM files WHERE absolutePath=:absolutePath");
            $stmt->bindParam(":absolutePath", $this->absolutePath);
            $stmt->execute();
            $res = $stmt->fetchAll();
            $exists = null;
            if (count($res) > 0) {
                $exists = true;
            } elseif (count($res) <= 0) {
                $exists = false;
            }
            return $exists;
        } catch (PDOException $e) {
            echo "Getting data from files failed: " . $e->getMessage();
            exit();
        }
    }

    /**
     * @return bool
     * true -> The entry for this file does not exist inside the database and was added
     * false -> The entry for this absolute path was already saved inside the database
     * You can use this method to add a file that was uploaded by a user to the database, so it is accessible inside the Panel
     */
    public function addFileToDatabase()
    {
        try {
            var_dump($this->userIDs);
            if (!$this->fileExistsInDatabase()) {
                $encodedTags = $this->encodeTags();
                $stmt = $this->dbh->prepare("INSERT INTO files (fileName, dir, relativePath, absolutePath, description, tags) VALUES (:fileName, :dir, :relativePath, :absolutePath, :describtion, :tags)");
                $stmt->bindParam(":fileName", $this->fileName);
                $stmt->bindParam(":dir", $this->dir);
                $stmt->bindParam(":relativePath", $this->relativePath);
                $stmt->bindParam(":absolutePath", $this->absolutePath);
                $stmt->bindParam(":describtion", $this->description);
                $stmt->bindParam(":tags", $encodedTags);
                $stmt->execute();

                //if (!empty($this->userIDs)) {
                    $this->reloadingData();
                    $this->addFileUserRelations();
                //}
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            echo "Adding file to files failed: " . $e->getMessage();
            exit();
        }
    }

    public function removeFileFromDatabase(){
        try {
            if($this->fileExistsInDatabase()){
                $this->removeFileUserRelations();
                $stmt = $this->dbh->prepare("DELETE from files where absolutePath=:absolutePath");
                $stmt->bindParam(":absolutePath", $this->absolutePath);
                $stmt->execute();
                return true;
            }
            return false;
        } catch (PDOException $e) {
            echo "Failed removing file from database: " . $e->getMessage();
            exit();
        }
    }
    /**
     * Creates relations between users and and a file
     * Will be used to determine whether a user can see a file inside its filemanager
     */
    private function addFileUserRelations()
    {
        try {
            var_dump($this->userIDs);
            foreach ($this->userIDs as $userID) {
                echo "<br>".$userID;
                $stmt = $this->dbh->prepare("INSERT INTO user_has_file (user_id, file_id) VALUES (:user_id, :file_id)");
                $stmt->bindParam(":file_id", $this->fileID);
                $stmt->bindParam(":user_id", $userID);
                $stmt->execute();
            }
        } catch (PDOException $e) {
            echo "Failed adding file user relations: " . $e->getMessage();
            exit();
        }
    }

    public function removeFileUserRelations()
    {
        try {
            $stmt = $this->dbh->prepare("DELETE from user_has_file where file_id = :file_id");
            $stmt->bindParam(":file_id", $this->fileID);
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Failed removing file user relations: " . $e->getMessage();
            exit();
        }
    }

    public function removeUserRelationToFileID($user_ID)
    {
        try {
            if($this->fileExistsInDatabase()){
                $stmt = $this->dbh->prepare("DELETE from user_has_file where file_id = :file_id AND user_id = :user_id");
                $stmt->bindParam(":file_id", $this->fileID);
                $stmt->bindParam(":user_id", $user_ID);
                $stmt->execute();
            }else{
                return false;
            }
        } catch (PDOException $e) {
            echo "Failed removing file user relations: " . $e->getMessage();
            exit();
        }
    }

    static public function removeAllUserRelations($user_ID){
        try {
            $stmt = Config::dbCon()->prepare("DELETE from user_has_file where user_id = :user_id");
            $stmt->bindParam(":user_id", $user_ID);
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Failed removing all file relations to a user: " . $e->getMessage();
            exit();
        }
    }



    /**
     * @return array
     * Returns the user id that can access the file
     * Will be used to share a file between users.
     */
    public function fetchUserRelationsToFileID()
    {
        try {
            $stmt = $this->dbh->prepare("SELECT user_id from user_has_file where file_id=:file_id");
            $stmt->bindParam(":file_id", $this->fileID);
            $stmt->execute();
            $res = $stmt->fetchAll();
            $data = array();
            $i = 0;
            foreach ($res as $entries) {
                $data[$i] = $entries[$i];
                $i++;
            }
            return $data;
        } catch (PDOException $e) {
            echo "Failed adding file user relations: " . $e->getMessage();
            exit();
        }
    }

    /**
     * @param string $description
     * Will be used to add or change the description for a file inside the object.
     */
    public function setDescription($description)
    {
        $this->description = htmlspecialchars($description);
    }

    /**
     * @param $tag
     * Adds a tag to the array list inside the object
     */
    public function addTag($tag)
    {
        $sizeofTag = count($this->tags);
        $this->tags[$sizeofTag] = $tag;
    }

    /**
     * @param $userID
     * Makes a file usable for a username
     */
    public function addUserID($userID)
    {
        settype($userID, "Integer");
        $sizeofuserIDs = count($this->userIDs);
        $this->userIDs[$sizeofuserIDs] = $userID;
    }

    /**
     * @return array|bool
     * array -> Contains the file data from the database
     * false -> There is no entry for this absolute path
     */
    private function fetchFileDataFormDatabase()
    {
        try {
            if ($this->fileExistsInDatabase()) {
                $stmt = $this->dbh->prepare("SELECT * from files where absolutePath=:absolutePath ");
                $stmt->bindParam(":absolutePath", $this->absolutePath);
                $stmt->execute();
                $res = $stmt->fetchAll();
                $data = array();
                foreach ($res as $enties) {
                    foreach ($enties as $key => $entry) {
                        $data[$key] = $entry;
                    }
                }
                return $data;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            echo "Getting data from files failed: " . $e->getMessage();
            exit();
        }
    }

    /**
     * @return string
     * Returns the content form the array as a comma separated string.
     * Will be used for the tag entry inside the database
     */
    private function encodeTags()
    {
        $encoded = "";
        foreach ($this->tags as $tag) {
            if (!empty($encoded))
                $encoded = $encoded . "," . htmlspecialchars($tag);
            else
                $encoded = $tag;
        }
        return $encoded;
    }

    /**
     * @param $encodedTags
     * @return array
     * Decodes a comma separated string into an array
     * Will be used to decode the string from the database entry, so it fits into the structure of the object
     */
    private function decodeTags($encodedTags)
    {
        $decodedTags = explode(",", $encodedTags);
        return $decodedTags;
    }

    /**
     * @return string
     * Returns an absolute path to the file, so a script can work with the file.
     * For example checking whether the file exists.
     */
    private function evaluateAbsolutePath()
    {
        return $_SERVER['DOCUMENT_ROOT'] . $this->evaluateRelativePath();
    }

    /**
     * @return string
     * Concatenates the relative path to the file and file name.
     * Please set the $jump variable of the Loader class with Loader:jump(), so the
     * returned path can be opened properly inside the browser.
     * This is useful for embedding files to a webpage.
     */
    private function evaluateRelativePath()
    {
        return $this->dir . DIRECTORY_SEPARATOR . $this->fileName;
    }

    /**
     * @return string
     * Returns the path were the file is located on the system.
     * Can be used to access a file and check for example whether it exists.
     * The absolute path is a unique identifier for the database.
     * It can look on Windows like that:
     * C:\webserver\dashboard\userfiles\1\cat.png
     * And it will be look on Linux like that:
     * /etc/var/www/dashboard/userfiles/1/cat.png
     */
    public function getAbsolutePath()
    {
        return $this->absolutePath;
    }

    /**
     * @return string
     * Returns the relative path were the file is located on the webserver
     * Can be used to ebbed a link to the file.
     * It can look like that:
     * /dashboard/userfiles/1/cat.png
     * <Panelfolder>/userfiles/<userID>/cat.png
     */
    public function getRelativePath()
    {
        return $this->relativePath;
    }
}