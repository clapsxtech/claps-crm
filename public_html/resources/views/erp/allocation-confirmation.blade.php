<fieldset><legend>{{ $recurring ?? false ? 'Confirm this weekly schedule with both parties' : 'Confirm this slot with both parties' }}</legend>
<label><input type="checkbox" name="teacher_confirmed" value="1" required> Teacher confirmed</label>
<label><input type="checkbox" name="student_confirmed" value="1" required> Parent / student confirmed</label>
<label>Confirmation channel<select name="confirmation_channel"><option value="whatsapp">WhatsApp</option><option value="call">Call</option></select></label>
<label>Confirmation evidence<textarea name="confirmation_note" required maxlength="4000" rows="2" placeholder="Who confirmed, when, and the agreed dates and times"></textarea></label>
</fieldset>
