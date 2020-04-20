/*------------Add user event handler------------*/
const apiUrl = "http://localhost/MacaroniPanel/scripts/userAdmin.php";

let pwInputName = "showPW";
let editPWName = "#editUserDialog input[name=\"pw\"]";

let pageBackButton = "pageBack";
let pageForwardButton = "pageForward";

//If the site has loaded, the event listeners will be added to the elements
document.addEventListener("DOMContentLoaded", () => {
    editPWName = document.getElementById(pwInputName);
    pwInputName = document.querySelector("#editUserDialog input[name=\"pw\"]");

    pageBackButton = document.getElementById(pageBackButton);
    pageForwardButton = document.getElementById(pageForwardButton);

    pageBackButton.addEventListener("click", ()=>{
        Table.currentSite--;
        Table.drawSite();
    });

    pageForwardButton.addEventListener("click", ()=>{
        Table.currentSite++;
        Table.drawSite();
    });

    //Setting up max entries
    const maxEntriesSelect = document.querySelector('select[name="maxEntries"]');
    let before = maxEntriesSelect.value;
    Table.maxEntries = maxEntriesSelect.value;
    maxEntriesSelect.addEventListener("focusout", () => {
        if (before != maxEntriesSelect.value) {
            Table.maxEntries = maxEntriesSelect.value;
            before = maxEntriesSelect.value;
            Table.currentSite = 1;
            Table.drawSite();
        }
    });

    //Getting Data from API
    Table.drawTableHeader();
    Table.getDataFromApi();

    //points at buttons that opens and closes the dialog
    const userAddButton = ".addUserButton";
    const userAddDialog = "#addUserDialog";
    const addUserSend = "#addUser";
    const userAddToggleElement = document.querySelectorAll(userAddButton);
    const userAddDialogElement = document.querySelector(userAddDialog);
    for (const userAddButton of userAddToggleElement) {
        userAddButton.addEventListener("click", (element) => {
            console.log(element);
            if (userAddDialogElement.classList.contains("open")) {
                closeElement(userAddDialog);
            } else {
                openElement(userAddDialog);
            }
        });
    }

    /*------User Add Send data----*/
    const addUserSendButton = document.querySelector(addUserSend);
    addUserSendButton.addEventListener("click", () => {
        const uid = document.querySelector('.signUp input[name="uid"]').value;
        const eMail = document.querySelector('.signUp input[name="e-mail"]').value;
        const pw = document.querySelector('.signUp input[name="pw"]').value;
        const type = document.querySelector('.signUp select[name="type"]').value;
        const publicCSRF = document.querySelector('#csrfToken').value;
        const xhr = new XMLHttpRequest();
        const url = `https://localhost/MacaroniPanel/scripts/userAdmin.php`;
        const params = `PHPSESSID=${getSessionID()}&uid=${uid}&email=${eMail}&pw=${pw}&type=${type}`;
        console.log(params);
        xhr.addEventListener("readystatechange", function () {
            console.log(this.readyState);
            if (this.readyState === 4) {
                console.log("Response from API");
                console.log(this.responseText);
            }
            if (this.status === 200) {
                const dialog = document.querySelector(".signUp .dialog");
                dialog.innerHTML = "Success";
                dialog.classList.add("success");
            }
        });
        // Add the required HTTP header for form data POST requests

        xhr.open("PUT", url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        //xhr.setRequestHeader('Content-type','application/json; charset=utf-8');
        xhr.send(params);
    });

    //Selectors above
    //opens the password field
    editPWName.addEventListener("click", ()=>{
        togglePWField();
    });
    const editUserButton = document.getElementById("editUser");
    editUserButton.addEventListener("click", ()=>{
        const uid = document.querySelectorAll('#editUserDialog input[name="uid"]').value;
        console.log(uid);

    });


});

function togglePWField() {
    if(pwInputName.classList.contains("hidden")){
        editPWName.classList.add("hidden");
        pwInputName.classList.remove("hidden");
    }else{
        editPWName.classList.remove("hidden");
        pwInputName.classList.add("hidden");
    }
    console.log(editPWName);
}

class Table {
    /**
     * @type array
     * Contains the data, that was requested from userUser.php script
     */
    static data;
    /**
     * @type {number}
     * maxEntries will be set when the site was loaded.
     * Will be modified, if the user changes the select input field of the page
     */
    static maxEntries;
    /**
     * @type {number}
     * The first page will be shown at default. Will be modified by the site button on the button.
     */
    static currentSite = 1;

    static getDataFromApi() {
        fetch(apiUrl)
            .then((response) => {
                return response.json();
            })
            .then((data) => {
                self.data = data;
                Table.drawSite();
            });
    }

    static drawSiteButtons() {
        const buttonContainer = document.querySelector("#clickableSites");
        buttonContainer.innerHTML = "";
        if(Number(this.currentSite) === 1) pageBackButton.classList.add("hidden");
        else pageBackButton.classList.remove("hidden");
        if(Number(this.currentSite) === Table.numOfPages()) pageForwardButton.classList.add("hidden");
        else pageForwardButton.classList.remove("hidden");

        for (let i = 1; i <= this.numOfPages(); i++) {
            let pageButtonContainer = document.createElement("div");
            let pageButton = document.createElement("a");
            pageButton.dataset.id = i;
            if (i === Number(Table.currentSite)) pageButton.classList.add("selected");

            pageButton.addEventListener("click", (element) => {
                this.currentSite = element.target.dataset.id;
                this.drawSite();
            });
            let page = document.createTextNode(i);
            buttonContainer.appendChild(pageButtonContainer).appendChild(pageButton).appendChild(page);

        }
    }

    static drawSite() {
        let page = Table.currentSite;
        const table = document.querySelector(".tableContent tbody");
        table.innerHTML = "";
        if (page < 1) page = 1;
        if (page > this.numOfPages()) page = this.numOfPages();
        let until = page * this.maxEntries;
        if (page * this.maxEntries > self.data.length) until = self.data.length;
        for (let i = (page - 1) * this.maxEntries; i < until; i++) {
            this.drawEntry(table, self.data[i]);
        }
        this.drawSiteButtons();
    }

    static drawEntry(table, element) {
        //User data
        let userData = [element.username, element.email, element.lastLogin, element.role];
        //Draws the container for the profile picture
        const entry = table.appendChild(document.createElement("tr"));
        const profilePicColumn = document.createElement("td");
        const profilePicContainer1 = document.createElement("div");
        profilePicContainer1.classList.add("row");
        const profilePicContainer2 = document.createElement("div");
        profilePicContainer2.classList.add("profilePicture");
        profilePicContainer2.innerHTML = element.profilePicture;
        entry.appendChild(profilePicColumn).appendChild(profilePicContainer1).appendChild(profilePicContainer2);

        userData.forEach((element) => {
            const data = document.createTextNode(element);
            const column = document.createElement("td");
            entry.appendChild(column).appendChild(data);
        });

        //action buttons
        let buttonContainer = document.createElement("div");
        const buttonColumn = document.createElement("td");
        buttonContainer = entry.appendChild(buttonColumn).appendChild(buttonContainer);
        const deleteAction = document.createElement("a");
        deleteAction.classList.add("col");
        deleteAction.classList.add("radial");
        deleteAction.classList.add("red");
        deleteAction.classList.add("deleteUser");
        deleteAction.dataset.username = element.username;
        const deleteIcon = document.createElement("img");
        deleteIcon.src = "../assets/icons/feather/trash-2.svg";
        buttonContainer.appendChild(deleteAction).appendChild(deleteIcon);

        const editDialog = "#editUserDialog";
        const editAction = document.createElement("a");
        editAction.classList.add("col");
        editAction.classList.add("radial");
        editAction.classList.add("editUser");
        editAction.dataset.username = element.username;
        editAction.dataset.email = element.email;
        editAction.dataset.role = element.role;
        editAction.addEventListener("click", (element)=>{
            if (document.querySelector(editDialog).classList.contains("open")) {
                closeElement(editDialog);
            } else{
                if(element.explicitOriginalTarget.parentElement.dataset.username != undefined){
                    openElement(editDialog);
                    document.querySelector('#editUserDialog input[name="uid"]').placeholder = element.explicitOriginalTarget.parentElement.dataset.username;
                    document.querySelector('#editUserDialog input[name="email"]').placeholder = element.explicitOriginalTarget.parentElement.dataset.email;
                    document.querySelector('#editUserDialog select[name="type"]').value = element.explicitOriginalTarget.parentElement.dataset.role;
                }
            }
        });

        const editIcon = document.createElement("img");
        editIcon.src = "../assets/icons/feather/edit.svg";
        buttonContainer.appendChild(editAction).appendChild(editIcon);

    }

    static numOfPages() {
        return Math.ceil(self.data.length / Table.maxEntries);
    }

    static drawTableHeader() {
        const table = document.querySelector(".tableContent thead");
        let headers = ["Profile", "Username", "E-Mail", "Last Login", "Role", "Actions"];
        const entry = document.createElement("tr");
        headers.forEach((element) => {
            const header = document.createElement("th");
            const profile = document.createTextNode(element);
            table.appendChild(entry).appendChild(header).appendChild(profile);
        })
    }
}
