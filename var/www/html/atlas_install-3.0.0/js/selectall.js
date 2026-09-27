function SetAllCheckBoxes(FormName, FieldName, obj)
{
        var CheckValue = document.forms[FormName].elements[obj].checked;
        if(!document.forms[FormName])
                return;
        var objCheckBoxes = document.forms[FormName].elements[FieldName];
        if(!objCheckBoxes)
                return;
        var countCheckBoxes = objCheckBoxes.length;
        if(!countCheckBoxes)
                objCheckBoxes.checked = CheckValue;
        else
                // set the check value for all check boxes
                for(var i = 0; i < countCheckBoxes; i++)
                        objCheckBoxes[i].checked = CheckValue;
}
