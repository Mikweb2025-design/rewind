# How Rewind works

1. **Journal.** Every create, write, rename, copy and delete inside `/<account>/files` is recorded (path, file id, time, device, flags).
2. **Plan.** To go back to the moment T, the entries after T are replayed newest first: renames are reversed, deleted files are restored from the
   trash bin, files created after T go to the trash, modified files get the newest version that is not newer than T.
3. **Run.** Each step is idempotent. Nothing is deleted for good: files removed by a rewind land in the trash and the replaced content stays as a version,
   so *undo* is simply a rewind to the moment just before the operation.
4. **Shield.** The same journal feeds the detector. A frozen device or account is made read only by a storage wrapper on the home storage;
   downloads, the trash bin and versions stay available so the rewind itself keeps working.
