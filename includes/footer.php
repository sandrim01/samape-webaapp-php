<?php if (isset($_SESSION['user_id'])): ?>
            </div> <!-- End of container-fluid -->
        </div> <!-- End of content -->
    </div> <!-- End of wrapper -->
<?php else: ?>
    </div> <!-- End of container -->
<?php endif; ?>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Main JavaScript -->
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    
    <?php if (isset($use_validation) && $use_validation): ?>
    <!-- Form Validation JavaScript -->
    <script src="<?= BASE_URL ?>/assets/js/validation.js"></script>
    <?php endif; ?>
    
    <?php if (isset($use_charts) && $use_charts): ?>
    <!-- Chart JavaScript -->
    <script src="<?= BASE_URL ?>/assets/js/charts.js"></script>
    <?php endif; ?>
    
    <?php if (isset($page_script)): ?>
    <!-- Page Specific JavaScript -->
    <script>
    <?= $page_script ?>
    </script>
    <?php endif; ?>
    
    <?php if (isset($extra_js)): ?>
    <?= $extra_js ?>
    <?php endif; ?>

</body>
</html>
