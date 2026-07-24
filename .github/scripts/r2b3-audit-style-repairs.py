from pathlib import Path

path = Path("backend/scripts/audit-business-contract.php")
source = path.read_text()
replacements = {
    "'static::updating' => 'Audit records must reject updates.'": "'::updating' => 'Audit records must reject updates.'",
    "'static::deleting' => 'Audit records must reject deletes.'": "'::deleting' => 'Audit records must reject deletes.'",
    "'static::updating' => 'Stock ledger entries must reject updates.'": "'::updating' => 'Stock ledger entries must reject updates.'",
    "'static::deleting' => 'Stock ledger entries must reject deletes.'": "'::deleting' => 'Stock ledger entries must reject deletes.'",
}
for old, new in replacements.items():
    if old not in source:
        raise RuntimeError(f"Expected append-only audit needle is missing: {old}")
    source = source.replace(old, new)
path.write_text(source)
