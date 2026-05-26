    </div>
</div>
<?php if (isset($pageScript) && is_string($pageScript) && $pageScript !== ''): ?>
<script src="<?php echo htmlspecialchars($pageScript); ?>"></script>
<?php endif; ?>
</body>
</html>
