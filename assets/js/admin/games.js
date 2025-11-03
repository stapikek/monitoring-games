function editGame(id, name, icon) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    var editIcon = document.getElementById('editIcon');
    if (editIcon) editIcon.value = icon || '';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEdit() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});


