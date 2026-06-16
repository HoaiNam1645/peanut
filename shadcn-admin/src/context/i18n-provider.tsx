'use client'

import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import type { AppLocale } from '@/lib/i18n/types'

const LOCALE_STORAGE_KEY = 'app_locale'

const uiMessages = {
  vi: {
    language: {
      label: 'Đổi ngôn ngữ',
      vietnamese: 'Tiếng Việt',
      english: 'Tiếng Anh',
    },
    sidebar: {
      workspace: {
        teamName: 'Không gian quản trị',
        teamPlan: 'Next.js + shadcn/ui',
        general: 'Tổng quan',
        overview: 'Tổng quan',
        tasks: 'Công việc',
        apps: 'Ứng dụng',
        users: 'Người dùng',
        support: 'Hỗ trợ',
        helpCenter: 'Trung tâm trợ giúp',
        notifications: 'Thông báo',
        settings: 'Cài đặt',
        profile: 'Hồ sơ',
      },
      lemiex: {
        teamName: 'Không gian Lemiex',
        teamPlan: 'Sidebar theo vai trò',
        overview: 'Tổng quan',
        commerce: 'Thương mại',
        operations: 'Vận hành',
        supportTools: 'Công cụ hỗ trợ',
        administration: 'Quản trị',
        dashboard: 'Bảng điều khiển',
        welcome: 'Chào mừng',
        orders: 'Đơn hàng',
        designs: 'Thiết kế',
        products: 'Sản phẩm',
        catalog: 'Danh mục',
        productVariants: 'Biến thể sản phẩm',
        stores: 'Cửa hàng',
        tickets: 'Khiếu nại',
        stockManagement: 'Quản lý kho',
        stockDashboard: 'Tổng quan kho',
        manageStock: 'Quản lý tồn kho',
        productions: 'Sản xuất',
        shortageReport: 'Báo cáo thiếu hàng',
        shortageByVariant: 'Thiếu hàng theo biến thể',
        auditLogs: 'Lịch sử kiểm tra',
        hrPayroll: 'Nhân sự & lương',
        attendances: 'Chấm công',
        payrollReport: 'Báo cáo lương',
        salaryTiers: 'Bậc lương',
        embroideryProgress: 'Tiến độ thêu',
        trackings: 'Theo dõi đơn',
        videos: 'Video',
        wallets: 'Ví',
        transactions: 'Giao dịch',
        pendingFund: 'Tiền chờ duyệt',
        refunds: 'Hoàn tiền',
        surcharge: 'Phụ thu',
        debits: 'Công nợ',
        quickAccess: {
          balance: 'Số dư',
          add: 'Nạp',
          orderIdPlaceholder: 'Order ID',
          openTrackPage: 'Mở trang track',
          scanQr: 'Quét QR',
          scanUnavailable: 'Tính năng quét QR sẽ được bổ sung sau.',
          scanInvalid: 'Mã QR không hợp lệ.',
          scanHttpsRequired: 'Cần HTTPS để dùng camera trên thiết bị này.',
          scanNotSupported: 'Trình duyệt không hỗ trợ camera.',
          scanCameraDenied: 'Không thể truy cập camera.',
          orderIdRequired: 'Vui lòng nhập Order ID',
          addFundTitle: 'Tạo yêu cầu nạp/rút tiền',
          addFundDescription: 'Gửi yêu cầu giao dịch ví để admin duyệt.',
          transactionId: 'Transaction ID',
          generateTransactionId: 'Tạo Transaction ID mới',
          processing: 'Đang xử lý...',
          submit: 'Gửi yêu cầu',
          addFundPending: 'Yêu cầu giao dịch đã được gửi và đang chờ duyệt.',
          addFundFailed: 'Không thể tạo yêu cầu giao dịch.',
        },
        staffReport: 'Báo cáo nhân sự',
        systems: 'Hệ thống',
        users: 'Người dùng',
        permissions: 'Phân quyền',
        tiers: 'Tiers',
      },
    },
    command: {
      placeholder: 'Tìm màn hình hoặc thao tác...',
      empty: 'Không tìm thấy kết quả.',
      theme: 'Giao diện',
      light: 'Sáng',
      dark: 'Tối',
      system: 'Theo hệ thống',
    },
    profile: {
      manageProfile: 'Hồ sơ cá nhân',
      billing: 'Thanh toán',
      notifications: 'Thông báo',
      signOut: 'Đăng xuất',
      roleLabel: 'Vai trò',
      signOutTitle: 'Đăng xuất',
      signOutDesc:
        'Bạn có chắc muốn đăng xuất không? Bạn sẽ cần đăng nhập lại để tiếp tục sử dụng tài khoản.',
      cancel: 'Hủy',
    },
    pagination: {
      rowsPerPage: 'Số dòng mỗi trang',
      pageOf: 'Trang {current} / {total}',
      goToFirstPage: 'Về trang đầu',
      goToPreviousPage: 'Về trang trước',
      goToPage: 'Tới trang {page}',
      goToNextPage: 'Tới trang sau',
      goToLastPage: 'Về trang cuối',
    },
    orders: {
      title: 'Đơn hàng',
      count: 'đơn hàng',
      refresh: 'Làm mới',
      embroidery: 'Thêu',
      print: 'In',
      loadErrorTitle: 'Không thể tải đơn hàng',
      empty: 'Không có đơn hàng phù hợp với bộ lọc hiện tại.',
      noOrderIds: 'Không có order ID nào khớp với bộ lọc hiện tại.',
      copiedOrderIds: 'Đã copy {count} order ID.',
      noTrackingNumbers: 'Không tìm thấy tracking cho các đơn đã chọn',
      copiedTrackingNumbers: 'Đã copy {count} tracking number(s)',
      copyTrackingFailed: 'Không thể copy mã tracking',
      selectAtLeastOneOrder: 'Vui lòng chọn ít nhất một đơn hàng',
      buyLabelFailed: 'Tạo vận chuyển thất bại',
      labelCreated: 'Tạo vận chuyển thành công! Tracking: {tracking}',
      labelJobsDispatched:
        'Đã gửi {count} đơn tạo vận chuyển thành công!',
      createOrder: 'Tạo đơn hàng',
      confirmBuyLabel: 'Xác nhận tạo vận chuyển',
      confirmBuyLabelDesc:
        'Bạn có chắc muốn tạo vận chuyển cho {count} đơn hàng không?',
      confirmPurchase: 'Xác nhận',
      processing: 'Đang xử lý...',
      copyTracking: 'Sao chép tracking',
      buyLabel: 'Create shipping (Tạo vận chuyển)',
      headers: {
        order: 'Đơn hàng',
        seller: 'Seller',
        ticket: 'Ticket',
        priority: 'Ưu tiên',
        embType: 'Loại thêu',
        fulfillStatus: 'Trạng thái xử lý',
        items: 'Sản phẩm',
        tracking: 'Tracking',
        printCost: 'Phí in',
        shipping: 'Ship',
        totalCost: 'Tổng chi phí',
        payment: 'Thanh toán',
        created: 'Ngày tạo',
        actions: 'Thao tác',
      },
      status: {
        unknown: 'Không xác định',
        noRefId: 'Không có ref ID',
        noVariant: 'Không có variant',
        hasTicket: 'Đã có ticket',
        normal: 'Bình thường',
        priority: 'Ưu tiên',
        noItems: 'Không có sản phẩm',
        itemCount: '{count} sản phẩm',
        noTracking: '-',
        label: 'Label',
        convert: 'Convert',
        na: 'N/A',
        unnamedItem: 'Sản phẩm chưa đặt tên',
        front: 'Front',
      },
      actions: {
        view: 'Xem',
        timeline: 'Timeline',
        edit: 'Sửa',
        support: 'Support',
        goToStores: 'Đi tới cửa hàng',
        ticketExistsTitle: 'Ticket đã tồn tại',
        ticketExistsDesc:
          'Đơn hàng này đã có một hoặc nhiều support ticket. Bạn muốn xem ticket hiện có hay tạo ticket mới?',
        viewExistingTickets: 'Xem ticket hiện có',
        createNewTicket: 'Tạo ticket mới',
        pending: 'Tính năng {label} sẽ được nối tiếp sau.',
        remakeDesign: 'Remake Des',
        remakeQr: 'Remake QR',
      },
      timelineModal: {
        title: 'Lịch sử đơn hàng',
        orderPrefix: 'Đơn hàng',
        loading: 'Đang tải timeline...',
        empty: 'Không tìm thấy sự kiện timeline',
        loadError: 'Không thể tải timeline',
        close: 'Đóng',
        columns: {
          action: 'Hành động',
          description: 'Mô tả',
          createdAt: 'Tạo lúc',
          updatedAt: 'Cập nhật lúc',
        },
      },
      detail: {
        backToOrders: 'Quay lại đơn hàng',
        loadingOrder: 'Đang tải chi tiết đơn hàng...',
        orderNotFound: 'Không tìm thấy đơn hàng',
        orderInfo: 'Thông tin đơn hàng',
        sellerInfo: 'Thông tin seller',
        shippingInfo: 'Thông tin vận chuyển',
        orderItems: 'Sản phẩm',
        pricing: 'Chi phí',
        actionsTitle: 'Thao tác',
        orderStt: 'Mã đơn',
        referenceId: 'Mã tham chiếu',
        sellerRef: 'Mã seller',
        paymentStatus: 'Trạng thái thanh toán',
        createdAt: 'Ngày tạo',
        username: 'Username',
        email: 'Email',
        tier: 'Tier',
        store: 'Cửa hàng',
        service: 'Đơn vị vận chuyển',
        method: 'Phương thức',
        trackingId: 'Mã tracking',
        address: 'Địa chỉ',
        shippingLabel: 'Shipping label',
        viewLabel: 'Xem label',
        convertLabel: 'Convert label',
        viewConvert: 'Xem convert',
        qrCodes: 'Mã QR',
        download: 'Tải xuống',
        downloadAll: 'Tải tất cả',
        downloadingAll: 'Đang tải...',
        downloadAllSuccess: 'Đã tải {success}/{total} mã QR',
        mergedImages: 'Ảnh ghép',
        quantity: 'Số lượng',
        printCost: 'Phí in',
        shippingCost: 'Phí ship',
        extraFee: 'Phụ phí',
        refundFee: 'Phí hoàn',
        totalCost: 'Tổng chi phí',
        profitMargin: 'Biên lợi nhuận',
        updatingLabel: 'Đang cập nhật label...',
        updateLabel: 'Cập nhật label',
        updateLabelSuccess: 'Cập nhật label thành công',
        updateLabelFailed: 'Cập nhật label thất bại',
        cancelOrder: 'Hủy đơn',
        sellerCancelConfirm:
          'Bạn có chắc muốn hủy đơn hàng #{id}? Hành động này không thể hoàn tác.',
        sellerCancelSuccess: 'Hủy đơn hàng thành công',
        sellerCancelFailed: 'Hủy đơn hàng thất bại',
        videos: 'Videos',
        noData: 'Không có dữ liệu',
      },
      createOrderDialog: {
        storeRequiredTitle: 'Cần có cửa hàng',
        storeRequiredDesc:
          'Bạn cần có ít nhất một cửa hàng trước khi tạo đơn hàng.',
        categoryTitle: 'Tạo đơn hàng mới',
        categoryDesc: 'Chọn nhóm sản phẩm để tiếp tục.',
        embroideryTitle: 'Thêu',
        embroideryDesc:
          'Áo thun, hoodie, sweatshirt với thiết kế thêu.',
        tumblerTitle: 'In cốc giữ nhiệt',
        tumblerDesc: 'Cốc giữ nhiệt và mug với thiết kế in.',
        typeTitle: 'Chọn loại đơn hàng',
        typeDescEmbroidery: 'Đơn hàng thêu',
        typeDescTumbler: 'Đơn hàng in',
        noDesignTitle: 'Không có thiết kế',
        noDesignDesc: 'Sản phẩm trơn không có file thiết kế.',
        labelShipTitle: 'Label Ship',
        labelShipDesc:
          'Đơn có file thiết kế và dùng nhãn vận chuyển TikTok.',
        sellerShipTitle: 'Seller Ship',
        sellerShipDesc:
          'Đơn có file thiết kế và địa chỉ nhận hàng.',
        tumblerLabelShipTitle: 'Label Ship',
        tumblerLabelShipDesc:
          'Đơn in dùng nhãn vận chuyển có sẵn.',
        tumblerSellerShipTitle: 'Seller Ship',
        tumblerSellerShipDesc:
          'Đơn in dùng địa chỉ nhận hàng.',
      },
      createForm: {
        labelShipTitle: 'Tạo đơn hàng - Label Ship',
        labelShipSubtitle:
          'Tạo đơn thêu với URL label TikTok và đầy đủ tài nguyên thiết kế.',
        sellerShipTitle: 'Tạo đơn hàng - Seller Ship',
        sellerShipSubtitle:
          'Tạo đơn thêu với địa chỉ người nhận và đầy đủ tài nguyên thiết kế.',
        backToOrders: 'Quay lại đơn hàng',
        orderInformation: 'Thông tin đơn hàng',
        shippingInformation: 'Thông tin vận chuyển',
        shippingAddress: 'Địa chỉ nhận hàng',
        productsAndDesignFiles: 'Sản phẩm & file thiết kế',
        productsAndDesignFilesDesc:
          'Thêm sản phẩm in, mockup và file thiết kế cho đơn hàng.',
        orderReferenceId: 'Mã tham chiếu đơn hàng',
        storeApiKey: 'Cửa hàng / API Key',
        sellerReference: 'Mã tham chiếu seller',
        orderStatus: 'Trạng thái đơn hàng',
        shippingMethod: 'Phương thức vận chuyển',
        shippingService: 'Đơn vị vận chuyển',
        fulfillmentPriority: 'Độ ưu tiên xử lý',
        shippingLabelUrl: 'URL label vận chuyển TikTok',
        shippingLabelHint:
          'Luồng này có chi phí ship thấp hơn. Không cần nhập địa chỉ người nhận.',
        orderNotes: 'Ghi chú đơn hàng',
        recipientName: 'Tên người nhận',
        phoneNumber: 'Số điện thoại',
        streetAddress: 'Địa chỉ',
        apartmentSuite: 'Căn hộ, suite, v.v.',
        city: 'Thành phố',
        stateProvince: 'Bang / Tỉnh',
        zipCode: 'Mã ZIP / bưu chính',
        country: 'Quốc gia',
        productCardTitle: 'Sản phẩm #{index}',
        productCardDesc:
          'Biến thể, mockup và file thiết kế của sản phẩm này.',
        productVariant: 'Biến thể sản phẩm',
        variantId: 'Variant ID',
        quantity: 'Số lượng',
        productName: 'Tên sản phẩm',
        mockupFrontUrl: 'URL mockup mặt trước',
        mockupBackUrl: 'URL mockup mặt sau',
        mockupPreview: 'Xem trước mockup',
        addFrontMockupUrl: 'Thêm URL mockup mặt trước',
        designFiles: 'File thiết kế',
        designFilesDesc:
          'Upload file thiết kế cho từng mặt của sản phẩm.',
        addDesignSide: 'Thêm mặt thiết kế',
        designTitle: 'Thiết kế #{index}',
        position: 'Vị trí',
        designFileUrl: 'URL file thiết kế',
        addProduct: 'Thêm sản phẩm',
        remove: 'Xóa',
        cancel: 'Hủy',
        createOrder: 'Tạo đơn hàng',
        creating: 'Đang tạo...',
        loadingStores: 'Đang tải cửa hàng...',
        selectedStore: 'Cửa hàng đã chọn: {name}',
        storesAvailable: '{count} cửa hàng khả dụng',
        noStoresFound: 'Không tìm thấy cửa hàng. Hãy nhập API key thủ công.',
        standardShippingMethod: 'standard',
        fixedUsps: 'USPS',
        optionLabels: {
          orderStatus: {
            new_order: 'Đơn mới',
            on_hold: 'Tạm giữ',
            confirm: 'Xác nhận',
            test_order: 'Đơn test',
          },
          shippingService: {
            USPS: 'USPS',
            UPS: 'UPS',
            FedEx: 'FedEx',
          },
          country: {
            US: 'Hoa Kỳ',
            CA: 'Canada',
            GB: 'Vương quốc Anh',
            AU: 'Úc',
            DE: 'Đức',
            FR: 'Pháp',
            JP: 'Nhật Bản',
            VN: 'Việt Nam',
          },
          designPosition: {
            front: 'Mặt trước',
            back: 'Mặt sau',
            neck: 'Cổ áo',
          },
        },
        productPicker: {
          product: 'Sản phẩm',
          size: 'Kích thước',
          loadingProducts: 'Đang tải sản phẩm...',
          selectProduct: 'Chọn sản phẩm',
          loadingSizes: 'Đang tải size...',
          selectSize: 'Chọn size',
          resolvingVariant: 'Đang lấy variant...',
          variantId: 'Variant ID',
          chooseAll: 'Chọn sản phẩm và kích thước để lấy variant',
        },
        upload: {
          upload: 'Upload',
          uploading: 'Đang upload...',
          uploadFailed: 'Upload thất bại',
          uploadImageOrPaste: 'Upload ảnh hoặc dán URL',
          previewAlt: 'Xem trước tệp',
        },
        placeholders: {
          orderRefId: 'vd. ORDER-12345',
          manualApiKey: 'Nhập API key thủ công',
          sellerRef: 'vd. SHOP-12345',
          selectStore: 'Chọn cửa hàng',
          selectStatus: 'Chọn trạng thái',
          selectShippingMethod: 'Chọn phương thức vận chuyển',
          selectShippingService: 'Chọn đơn vị vận chuyển',
          selectPriority: 'Chọn độ ưu tiên',
          shippingLabel:
            'https://open-fs.tiktokshops.us/label/12345.pdf',
          notes: 'Thêm ghi chú hoặc hướng dẫn xử lý',
          recipientName: 'Nguyen Van A',
          phone: '+84901234567',
          street1: '123 Main Street',
          street2: 'Apartment, suite, unit, building, floor',
          city: 'Ho Chi Minh City',
          state: 'NY',
          zip: '10001',
          selectCountry: 'Chọn quốc gia',
          variantId: 'Chọn sản phẩm và size',
          productName: 'Tên sản phẩm hiển thị trong đơn',
          mockupFront: 'https://example.com/mockup-front.png',
          mockupBack: 'https://example.com/mockup-back.png',
          selectPosition: 'Chọn vị trí',
          designFileUrl: 'https://example.com/design.png',
        },
        validation: {
          orderRefRequired: 'Mã tham chiếu đơn hàng là bắt buộc.',
          apiKeyRequired: 'Cửa hàng / API key là bắt buộc.',
          shippingLabelRequired: 'URL label vận chuyển là bắt buộc.',
          shippingAddressRequired: 'Vui lòng nhập đầy đủ địa chỉ giao hàng.',
          variantRequired: 'Mỗi sản phẩm phải có variant ID.',
          productNameRequired: 'Mỗi sản phẩm phải có tên sản phẩm.',
          mockupRequired: 'Mỗi sản phẩm phải có URL mockup mặt trước.',
          designFileRequired:
            'Mỗi sản phẩm phải có ít nhất một file thiết kế.',
        },
        submit: {
          successWithId: 'Tạo đơn hàng thành công. Order ID: {id}',
          success: 'Tạo đơn hàng thành công.',
          failed: 'Tạo đơn hàng thất bại',
        },
      },
      editForm: {
        title: 'Chỉnh sửa đơn hàng',
        reference: 'Mã tham chiếu',
        loading: 'Đang tải chi tiết đơn hàng...',
        loadingFailed: 'Không thể tải chi tiết đơn hàng',
        cannotEdit: 'Không thể chỉnh sửa',
        sellerBlockReason:
          'Seller chỉ có thể chỉnh sửa đơn ở trạng thái new_order hoặc on_hold. Trạng thái hiện tại: {status}',
        generalInformation: 'Thông tin chung',
        shippingDetails: 'Thông tin vận chuyển',
        addressInformation: 'Thông tin địa chỉ',
        orderItems: 'Sản phẩm',
        note: 'Ghi chú',
        shippingMethod: 'Phương thức vận chuyển',
        shippingService: 'Đơn vị vận chuyển',
        shippingLabelUrl: 'URL shipping label',
        addressLine1: 'Địa chỉ dòng 1',
        addressLine2: 'Địa chỉ dòng 2',
        fullName: 'Họ và tên',
        city: 'Thành phố',
        state: 'Bang / Tỉnh',
        zipCode: 'Mã ZIP / bưu chính',
        country: 'Quốc gia',
        phone: 'Số điện thoại',
        mockupImages: 'Ảnh mockup',
        frontViewUrl: 'URL ảnh mặt trước',
        backViewUrl: 'URL ảnh mặt sau',
        printFilesDesigns: 'Print files / Designs',
        addPosition: 'Thêm vị trí',
        noPrintFiles: 'Chưa có print file nào.',
        positionPlaceholder: 'Vị trí...',
        url: 'URL',
        imageUrl: 'URL ảnh',
        pdfUrl: 'URL PDF',
        embUrl: 'URL EMB',
        pesUrl: 'URL PES',
        cancel: 'Hủy',
        saveChanges: 'Lưu thay đổi',
        saving: 'Đang lưu...',
        saveSuccess: 'Cập nhật đơn hàng thành công',
        noChanges: 'Không có thay đổi nào',
        saveFailed: 'Cập nhật đơn hàng thất bại',
        viewFile: 'Xem file',
        changeVariant: 'Đổi variant',
        currentVariant: 'Variant hiện tại',
        newVariant: 'Variant mới',
        variantChangeLocked:
          'Chỉ có thể đổi variant khi đơn chưa thanh toán và đang ở trạng thái new_order hoặc on_hold.',
        revertVariant: 'Hoàn tác',
        variantChangedHint: 'Màu/size và giá sẽ được cập nhật sau khi lưu.',
      },
      filters: {
        orderId: 'MÃ ĐƠN HÀNG',
        variantId: 'MÃ BIẾN THỂ',
        refId: 'MÃ THAM CHIẾU',
        trackingNumber: 'MÃ TRACKING',
        search: 'Tìm kiếm',
        clearAll: 'Xóa bộ lọc',
        getIds: 'Lấy IDs',
        filters: 'Bộ lọc',
        excludeStatus: 'LOẠI TRỪ TRẠNG THÁI',
        shippingInfo: 'THÔNG TIN SHIP',
        missingShippingInfo: 'Thiếu thông tin (Label/Tracking/Convert)',
        fulfillStatus: 'TRẠNG THÁI XỬ LÝ',
        paymentStatus: 'TRẠNG THÁI THANH TOÁN',
        productAttributes: 'THUỘC TÍNH SẢN PHẨM',
        style: 'KIỂU',
        color: 'MÀU',
        size: 'KÍCH THƯỚC',
        seller: 'NGƯỜI BÁN',
        embType: 'LOẠI THÊU',
        productName: 'TÊN SẢN PHẨM',
        dateFrom: 'TỪ NGÀY',
        dateTo: 'ĐẾN NGÀY',
        shippedDateRange: 'NGÀY ĐI ĐƠN (ĐỐI CHIẾU VẬN CHUYỂN)',
        shippedDateFrom: 'NGÀY ĐI TỪ',
        shippedDateTo: 'NGÀY ĐI ĐẾN',
        shippedToday: 'Hôm nay',
        shippedDateHint:
          'Mốc 12h trưa: chọn 1 ngày = lô rời xưởng trưa ngày đó (gồm đơn quét từ 12h trưa hôm trước đến 12h trưa ngày đã chọn).',
        sortBy: 'SẮP XẾP THEO',
        sortOrder: 'THỨ TỰ SẮP XẾP',
        placeholders: {
          orderId: 'vd. 59 58 80',
          variantId: 'Mã biến thể',
          refId: 'Mã tham chiếu',
          trackingNumber: 'Mã tracking',
          selectStyle: 'Chọn style',
          selectColor: 'Chọn màu',
          selectSize: 'Chọn size',
          allSellers: 'Tất cả seller',
          allTypes: 'Tất cả loại',
          productName: 'Tên sản phẩm',
          createdDate: 'Ngày tạo',
          ascending: 'Tăng dần',
        },
        selectStyle: 'Chọn style',
        selectColor: 'Chọn màu',
        selectSize: 'Chọn size',
        allSellers: 'Tất cả seller',
        allTypes: 'Tất cả loại',
      },
      paymentStatuses: {
        pending: 'Chờ thanh toán',
        paid: 'Đã thanh toán',
        partial_refund: 'Hoàn tiền một phần',
        refunded: 'Đã hoàn tiền',
        failed: 'Thất bại',
      },
      fulfillStatuses: {
        new_order: 'Đơn mới',
        confirm: 'Xác nhận',
        pending_stock: 'Chờ hàng',
        in_stock: 'Có hàng',
        producing: 'Đang sản xuất',
        qc_pass: 'QC đạt',
        packed: 'Đã đóng gói',
        shipped: 'Đã giao',
        on_hold: 'Tạm giữ',
        return_to_support: 'Trả về support',
        cancelled: 'Đã hủy',
        cancelled_refund_shipping: 'Đã hủy (hoàn ship)',
        closed: 'Đã đóng',
        test_order: 'Đơn test',
      },
      sortBy: {
        created_at: 'Ngày tạo',
        updated_at: 'Ngày cập nhật',
        shipped_at: 'Ngày đi đơn',
        id: 'Order ID',
        ref_id: 'Reference ID',
      },
      sortOrder: {
        asc: 'Tăng dần',
        desc: 'Giảm dần',
      },
    },
    productVariants: {
      title: 'Biến thể sản phẩm',
      count: 'sản phẩm',
      loading: 'Đang tải sản phẩm...',
      loadError: 'Không thể tải danh sách sản phẩm',
      empty: 'Không có sản phẩm nào khớp với bộ lọc hiện tại.',
      tabs: {
        embroidery: 'Thêu',
        print: 'In',
      },
      columns: {
        product: 'Sản phẩm',
        templateUrl: 'Template',
        colors: 'Màu',
        sizes: 'Kích thước',
        variants: 'Variants',
        totalStock: 'Tồn kho',
        priceRange: 'Khoảng giá',
        status: 'Trạng thái',
        actions: 'Thao tác',
      },
      filters: {
        search: 'Tìm kiếm',
        searchPlaceholder: 'Tìm theo tên, brand, style...',
        style: 'Style',
        stylePlaceholder: 'Lọc theo style...',
        brand: 'Brand',
        brandPlaceholder: 'Lọc theo brand...',
        status: 'Trạng thái',
        allStatus: 'Tất cả trạng thái',
        sortBy: 'Sắp xếp',
        newestFirst: 'Mới nhất trước',
        oldestFirst: 'Cũ nhất trước',
        nameAz: 'Tên (A-Z)',
        nameZa: 'Tên (Z-A)',
        brandAz: 'Brand (A-Z)',
        brandZa: 'Brand (Z-A)',
        clearFilters: 'Xóa bộ lọc',
      },
      status: {
        noBrand: 'Chưa có brand',
        noStyle: 'Chưa có style',
        noTemplate: 'Chưa có template',
        noColors: 'Không có màu',
        noSizes: 'Không có size',
        active: 'đang hoạt động',
        activeLabel: 'Hoạt động',
        inactiveLabel: 'Tạm tắt',
        na: 'N/A',
        to: 'đến',
      },
      actions: {
        importCsv: 'Import CSV',
        createProduct: 'Tạo sản phẩm',
        importPending: 'Flow import CSV sẽ được nối tiếp sau.',
        stock: 'Stock',
        view: 'Xem',
        delete: 'Xóa',
        confirmDelete: 'Bạn có chắc muốn xóa sản phẩm "{name}"?',
        deleteSuccess: 'Đã xóa sản phẩm thành công',
        deleteFailed: 'Xóa sản phẩm thất bại',
        deletePending: 'Flow xóa sản phẩm "{name}" sẽ được nối tiếp sau.',
      },
      importDialog: {
        title: 'Import sản phẩm từ CSV',
        description: 'Upload file CSV, xem trước dữ liệu rồi import vào hệ thống.',
        downloadTemplate: 'Tải template',
        downloadCurrentData: 'Tải dữ liệu hiện tại',
        clickToSelect: 'Bấm để chọn file CSV',
        orDragDrop: 'hoặc kéo thả vào đây',
        selectCsvFile: 'Vui lòng chọn file CSV',
        preview: 'Xem trước',
        previewFailed: 'Không thể xem trước file CSV',
        import: 'Import',
        importSuccess: 'Import sản phẩm thành công',
        importFailed: 'Import sản phẩm thất bại',
        products: 'Sản phẩm',
        newProducts: 'Sản phẩm mới',
        existingProducts: 'Sản phẩm cập nhật',
        newTag: 'MỚI',
        updateTag: 'CẬP NHẬT',
        imported: 'Đã import',
        failed: 'Lỗi',
        errors: 'Danh sách lỗi',
        done: 'Hoàn tất',
      },
      stockDialog: {
        title: 'Cập nhật tồn kho',
        description: 'Điều chỉnh tồn kho cho biến thể sản phẩm.',
        addStock: 'Thêm kho',
        subtractStock: 'Trừ kho',
        color: 'Màu',
        size: 'Kích thước',
        quantity: 'Số lượng',
        quantityPlaceholder: 'Nhập số lượng',
        selectColor: 'Chọn màu',
        selectSize: 'Chọn size',
        validation: 'Vui lòng nhập đầy đủ thông tin tồn kho hợp lệ.',
        updating: 'Đang cập nhật...',
        updateFailed: 'Cập nhật tồn kho thất bại',
        addSuccess: 'Đã thêm tồn kho thành công',
        subtractSuccess: 'Đã trừ tồn kho thành công',
      },
      detail: {
        loading: 'Đang tải chi tiết sản phẩm...',
        loadError: 'Không thể tải chi tiết sản phẩm',
        notFound: 'Không tìm thấy sản phẩm',
        back: 'Quay lại biến thể sản phẩm',
        active: 'Hoạt động',
        inactive: 'Tạm tắt',
        brand: 'Brand',
        style: 'Style',
        warehouse: 'Kho',
        category: 'Danh mục',
        print: 'In',
        embroidery: 'Thêu',
        created: 'Ngày tạo',
        updated: 'Cập nhật',
        editProduct: 'Chỉnh sửa sản phẩm',
        totalVariants: 'Tổng biến thể',
        totalStock: 'Tổng tồn kho',
        priceRange: 'Khoảng giá',
        colors: 'Màu',
        sizes: 'Kích thước',
        variantsTitle: 'Danh sách biến thể',
        variantsCount: 'biến thể',
        noData: 'N/A',
        save: 'Lưu',
        cancel: 'Hủy',
        edit: 'Sửa',
        delete: 'Xóa',
        confirmDeleteVariant: 'Bạn có chắc muốn xóa biến thể {id}?',
        deleteVariantSuccess: 'Đã xóa biến thể thành công',
        deleteVariantFailed: 'Xóa biến thể thất bại',
        deletePending: 'Luồng xóa biến thể {id} sẽ được nối tiếp sau.',
        variantUpdated: 'Cập nhật biến thể thành công',
        updateFailed: 'Cập nhật biến thể thất bại',
        pricingSaved: 'Cập nhật bảng giá thành công',
        viewPricing: 'Xem giá',
        setPricing: 'Thiết lập giá',
        pricing: {
          title: 'Bảng giá theo tier',
          noVariant: 'Chưa chọn biến thể',
          readOnly: 'Chỉ xem',
          production: 'Chi phí sản xuất',
          shipping: 'Chi phí vận chuyển',
          type: 'Loại giá',
          close: 'Đóng',
          cancel: 'Hủy',
          saving: 'Đang lưu...',
          save: 'Lưu thay đổi',
          failed: 'Cập nhật bảng giá thất bại',
        },
        columns: {
          variantId: 'Variant ID',
          color: 'Màu',
          size: 'Kích thước',
          stock: 'Tồn kho',
          supplierPrice: 'Giá NCC',
          tierPricing: 'Bảng giá',
          weight: 'Khối lượng',
          dimensions: 'Kích thước',
          status: 'Trạng thái',
          actions: 'Thao tác',
        },
      },
      createForm: {
        title: 'Tạo sản phẩm',
        description: 'Tạo sản phẩm mới và khai báo biến thể cùng bảng giá.',
        productInfo: 'Thông tin sản phẩm',
        variants: 'Biến thể',
        pricing: 'Bảng giá',
        productName: 'Tên sản phẩm',
        style: 'Style',
        brand: 'Nhà cung cấp',
        warehouse: 'Kho',
        productNamePlaceholder: 'vd. Áo thun in',
        stylePlaceholder: 'vd. Box-S',
        brandPlaceholder: 'vd. Xưởng In Việt',
        warehousePlaceholder: 'vd. Main Warehouse',
        mockupUrl: 'Mockup URL',
        category: 'Danh mục',
        status: 'Trạng thái',
        active: 'Hoạt động',
        inactive: 'Tạm tắt',
        addVariant: 'Thêm biến thể',
        noVariantsYet: 'Chưa có biến thể nào. Hãy bấm thêm biến thể để bắt đầu.',
        variant: 'Biến thể',
        removeVariant: 'Xóa biến thể',
        variantId: 'Variant ID',
        variantIdPlaceholder: 'vd. G5000-BLK-S',
        sku: 'SKU',
        skuPlaceholder: 'vd. SKU-G5000-BLK-S',
        color: 'Màu',
        colorPlaceholder: 'vd. Black',
        size: 'Kích thước',
        sizePlaceholder: 'vd. S',
        stock: 'Tồn kho',
        supplierPrice: 'Giá NCC',
        weight: 'Khối lượng (g)',
        dimensions: 'Kích thước (D x R x C)',
        addPrice: 'Thêm giá',
        noPricesAdded: 'Chưa có giá nào',
        tier: 'Tier',
        priceType: 'Loại giá',
        price: 'Giá',
        cancel: 'Hủy',
        create: 'Tạo sản phẩm',
        creating: 'Đang tạo...',
        productNameRequired: 'Tên sản phẩm là bắt buộc',
        variantIdRequired: 'Variant ID là bắt buộc',
        createSuccess: 'Tạo sản phẩm thành công',
        createFailed: 'Tạo sản phẩm thất bại',
      },
    },
    storesPage: {
      title: 'Quản lý cửa hàng',
      subtitle: 'Quản lý toàn bộ cửa hàng',
      totalStores: 'cửa hàng',
      addStore: 'Thêm cửa hàng',
      searchPlaceholder: 'Tìm theo tên cửa hàng, username hoặc email...',
      allStatus: 'Tất cả trạng thái',
      loading: 'Đang tải cửa hàng...',
      noStores: 'Không tìm thấy cửa hàng',
      noStoresAvailable: 'Chưa có cửa hàng nào',
      failedToLoad: 'Không thể tải danh sách cửa hàng',
      columns: {
        id: 'ID',
        user: 'Người dùng',
        storeName: 'Tên cửa hàng',
        status: 'Trạng thái',
        createdAt: 'Ngày tạo',
        actions: 'Thao tác',
      },
      status: {
        active: 'Active',
        unconfirmed: 'Unconfirmed',
        banned: 'Banned',
      },
      dialog: {
        createTitle: 'Thêm cửa hàng mới',
        createSubtitle: 'Tạo cửa hàng mới cho seller',
        editTitle: 'Chỉnh sửa cửa hàng',
        editSubtitle: 'Cập nhật thông tin cửa hàng',
        loadingUsers: 'Đang tải danh sách người dùng...',
        loadingStore: 'Đang tải dữ liệu cửa hàng...',
        user: 'Người dùng (Seller)',
        selectUser: 'Chọn người dùng',
        storeName: 'Tên cửa hàng',
        enterStoreName: 'Nhập tên cửa hàng',
        apiKey: 'API Key',
        status: 'Trạng thái',
        cancel: 'Hủy',
        create: 'Tạo cửa hàng',
        creating: 'Đang tạo...',
        update: 'Cập nhật cửa hàng',
        updating: 'Đang cập nhật...',
        onlySelf: 'Bạn chỉ có thể tạo cửa hàng cho chính mình',
        onlyAdmin: 'Chỉ Admin mới có thể thay đổi người sở hữu cửa hàng',
        statusHint: 'Thao tác này sẽ cập nhật trạng thái người dùng',
        apiKeyHint: 'API key được tạo tự động. Bấm làm mới để tạo key mới.',
        apiKeyEditHint: 'Bấm làm mới để tạo API key mới',
        refreshKey: 'Tạo API key mới',
        successCreate: 'Tạo cửa hàng thành công!',
        successUpdate: 'Cập nhật cửa hàng thành công!',
        failedCreate: 'Tạo cửa hàng thất bại. Vui lòng thử lại.',
        failedUpdate: 'Cập nhật cửa hàng thất bại. Vui lòng thử lại.',
        failedLoadUsers: 'Không thể tải danh sách người dùng. Vui lòng thử lại.',
        failedLoadStore: 'Không thể tải dữ liệu cửa hàng. Vui lòng thử lại.',
        validation: {
          requiredUser: 'Vui lòng chọn người dùng',
          requiredName: 'Tên cửa hàng là bắt buộc',
          requiredApiKey: 'API Key là bắt buộc',
        },
        active: 'Active',
        unconfirmed: 'Unconfirmed',
        banned: 'Banned',
      },
    },
    usersPage: {
      title: 'Quản lý người dùng',
      addFund: 'Nạp tiền',
      addNew: 'Thêm người dùng',
      backToList: 'Quay lại danh sách',
      backToDetail: 'Quay lại chi tiết',
      createTitle: 'Thêm người dùng',
      editTitle: 'Chỉnh sửa người dùng',
      viewTitle: 'Chi tiết người dùng',
      accountInfo: 'Thông tin tài khoản',
      userDetails: 'Thông tin người dùng',
      integrationSettings: 'Thiết lập tích hợp',
      debitSettings: 'Thiết lập công nợ',
      additionalOptions: 'Tùy chọn thêm',
      username: 'Username',
      email: 'Email',
      role: 'Vai trò',
      statusLabel: 'Trạng thái',
      registrationDate: 'Ngày đăng ký',
      firstName: 'Tên',
      lastName: 'Họ',
      phone: 'Số điện thoại',
      dob: 'Ngày sinh',
      address: 'Địa chỉ',
      webhookUrl: 'Webhook URL',
      telegramId: 'Telegram ID',
      apiKey: 'API Key',
      maxDebit: 'Công nợ tối đa',
      maxDateDebit: 'Số ngày công nợ tối đa',
      minDateDebit: 'Số ngày công nợ tối thiểu',
      balanceLabel: 'Số dư',
      supportUs: 'Support Us',
      privateSeller: 'Private Seller',
      days: 'ngày',
      yes: 'Có',
      no: 'Không',
      filters: {
        search: 'Tìm theo tên, email, username...',
        allStatus: 'Tất cả trạng thái',
        allRoles: 'Tất cả vai trò',
        allTiers: 'Tất cả tier',
      },
      status: {
        active: 'Hoạt động',
        unconfirmed: 'Chưa xác nhận',
        banned: 'Đã khóa',
      },
      columns: {
        username: 'Username',
        fullName: 'Họ tên',
        role: 'Vai trò',
        email: 'Email',
        balance: 'Số dư',
        tier: 'Tier',
        registrationDate: 'Ngày đăng ký',
        status: 'Trạng thái',
        actions: 'Thao tác',
      },
      form: {
        accountInfo: 'Thông tin tài khoản',
        userDetails: 'Thông tin người dùng',
        integrationSettings: 'Thiết lập tích hợp',
        debitSettings: 'Thiết lập công nợ',
        additionalOptions: 'Tùy chọn thêm',
        email: 'Email',
        username: 'Username',
        password: 'Mật khẩu',
        confirmPassword: 'Xác nhận mật khẩu',
        newPassword: 'Mật khẩu mới',
        confirmNewPassword: 'Xác nhận mật khẩu mới',
        leaveBlank: 'Để trống nếu muốn giữ mật khẩu hiện tại',
        role: 'Vai trò',
        status: 'Trạng thái',
        firstName: 'Tên',
        lastName: 'Họ',
        phone: 'Số điện thoại',
        dob: 'Ngày sinh',
        address: 'Địa chỉ',
        webhookUrl: 'Webhook URL',
        telegramId: 'Telegram ID',
        apiKey: 'API Key',
        maxDebit: 'Công nợ tối đa',
        maxDateDebit: 'Số ngày công nợ tối đa',
        minDateDebit: 'Số ngày công nợ tối thiểu',
        supportUs: 'Support Us',
        yes: 'Có',
        no: 'Không',
        optional: '(không bắt buộc)',
        loadingRoles: 'Đang tải vai trò...',
        noRoles: 'Không có vai trò',
        submit: 'Tạo người dùng',
        update: 'Cập nhật người dùng',
        cancel: 'Hủy',
      },
      addFundModal: {
        title: 'Nạp tiền cho seller',
        selectSeller: 'Chọn seller',
        loadingSellers: 'Đang tải seller...',
        selectPlaceholder: '-- Chọn seller --',
        currentBalance: 'Số dư hiện tại',
        type: 'Loại',
        deposit: 'Nạp tiền (+)',
        withdraw: 'Trừ tiền (-)',
        amount: 'Số tiền',
        enterAmount: 'Nhập số tiền',
        note: 'Ghi chú',
        notePlaceholder: 'VD: Nạp tiền hàng tháng',
        newBalance: 'Số dư mới',
        cancel: 'Hủy',
        submit: 'Xác nhận',
        selectSellerRequired: 'Vui lòng chọn seller',
        invalidAmount: 'Vui lòng nhập số tiền hợp lệ',
        fundFailed: 'Nạp tiền thất bại',
        fundSuccess:
          'Đã {action} ${amount} {direction} {user}. Số dư mới: ${balance}',
      },
      tiers: {
        silver: 'Silver',
        gold: 'Gold',
        platinum: 'Platinum',
        diamond: 'Diamond',
      },
      roles: {
        admin: 'Admin',
        seller: 'Seller',
        user: 'User',
        supplier: 'Supplier',
        staff: 'Staff',
        support: 'Support',
        designer: 'Designer',
        finance: 'Finance',
      },
      notFound: 'Không tìm thấy người dùng',
      loadFailed: 'Không thể tải thông tin người dùng',
      deleteConfirm: 'Bạn có chắc muốn xóa người dùng này không?',
      deleteSuccess: 'Xóa người dùng thành công',
      deleteFailed: 'Xóa người dùng thất bại',
      createSuccess: 'Tạo người dùng thành công!',
      updateSuccess: 'Cập nhật người dùng thành công!',
      loading: 'Đang tải...',
      deleteTitle: 'Xóa',
      error: 'Có lỗi xảy ra',
      na: 'N/A',
    },
    permissionsPage: {
      title: 'Phân quyền',
      subtitle: 'Quản lý ma trận phân quyền theo vai trò',
      syncPermissions: 'Đồng bộ quyền',
      syncing: 'Đang đồng bộ...',
      permission: 'Quyền',
      save: 'Lưu',
      saving: 'Đang lưu...',
      adminHasAllPermissions: 'Admin có toàn bộ quyền',
      savePermissions: 'Lưu phân quyền',
      selectAllInGroup: 'Chọn tất cả trong nhóm',
      noPermissions: 'Không tìm thấy quyền nào',
      loadFailed: 'Không thể tải dữ liệu phân quyền',
      saveSuccess: 'Lưu phân quyền thành công',
      saveFailed: 'Không thể lưu phân quyền',
      syncSuccess: 'Đồng bộ quyền thành công',
      syncFailed: 'Không thể đồng bộ quyền',
      otherGroup: 'Khác',
      createRole: 'Tạo role',
      newRoleTitle: 'Tạo role mới',
      newRoleDescription: 'Thêm role mới. Bạn có thể gán quyền sau khi tạo.',
      roleName: 'Tên role (system)',
      roleNamePlaceholder: 'VD: Manager (chỉ chữ, số, gạch dưới)',
      roleDisplayName: 'Tên hiển thị',
      roleDisplayNamePlaceholder: 'VD: Quản lý cấp cao',
      roleDescription: 'Mô tả',
      roleDescriptionPlaceholder: 'Mô tả ngắn (tuỳ chọn)',
      cancel: 'Huỷ',
      create: 'Tạo',
      creating: 'Đang tạo...',
      createSuccess: 'Tạo role thành công',
      createFailed: 'Không thể tạo role',
      deleteRole: 'Xoá role',
      confirmDelete: 'Bạn có chắc muốn xoá role này?',
      builtInRole: 'Role mặc định (không thể xoá)',
      deleteSuccess: 'Xoá role thành công',
      deleteFailed: 'Không thể xoá role',
    },
    tiersPage: {
      title: 'Tiers',
      createTier: 'Tạo tier',
      loading: 'Đang tải tiers...',
      noTiers: 'Chưa có tier nào',
      tierBadge: 'Tier',
      extraFees: 'Phí cộng thêm',
      refundFees: 'Phí hoàn',
      embroideryFees: 'Phí thêu',
      priorityFees: 'Phí ưu tiên',
      addExtraFee: 'Thêm phí cộng thêm',
      addRefundFee: 'Thêm phí hoàn',
      addEmbroideryFee: 'Thêm phí thêu',
      addPriorityFee: 'Thêm phí ưu tiên',
      emptyExtraFees: 'Chưa cấu hình phí cộng thêm',
      emptyRefundFees: 'Chưa cấu hình phí hoàn',
      emptyEmbroideryFees: 'Chưa cấu hình phí thêu',
      emptyPriorityFees: 'Chưa cấu hình phí ưu tiên',
      minStitch: 'Stitch tối thiểu',
      maxStitch: 'Stitch tối đa',
      amount: 'Số tiền ($)',
      stitch: 'Stitch',
      type: 'Loại',
      name: 'Tên',
      displayName: 'Tên hiển thị',
      description: 'Mô tả',
      price: 'Giá ($)',
      actions: 'Thao tác',
      edit: 'Sửa',
      delete: 'Xóa',
      createTitle: 'Tạo tier',
      editTitle: 'Chỉnh sửa tier',
      tierName: 'Tên tier',
      tierNamePlaceholder: 'Nhập tên tier',
      save: 'Lưu',
      cancel: 'Hủy',
      creating: 'Đang tạo...',
      saving: 'Đang lưu...',
      deleting: 'Đang xóa...',
      confirmDeleteTitle: 'Xác nhận xóa',
      confirmDeleteDescription: 'Hành động này không thể hoàn tác.',
      extraFeeDialogTitle: 'Phí cộng thêm',
      refundFeeDialogTitle: 'Phí hoàn',
      embroideryFeeDialogTitle: 'Phí thêu',
      priorityFeeDialogTitle: 'Phí ưu tiên',
      embroideryType: 'Loại thêu',
      embroideryTypePlaceholder: 'Chọn loại thêu',
      priorityName: 'Tên mức ưu tiên',
      priorityDisplayNamePlaceholder: 'Ưu tiên',
      priorityDescriptionPlaceholder: 'Xử lý tiêu chuẩn 3-5 ngày',
      standard: 'Standard',
      metallic: 'Metallic',
      glow: 'Glow',
      puff: 'Puff',
      normalPriority: 'Thường',
      rushPriority: 'Ưu tiên',
      requiredTierName: 'Tên tier là bắt buộc',
      requiredFields: 'Vui lòng nhập đầy đủ các trường bắt buộc',
      tierCreated: 'Tạo tier thành công',
      tierUpdated: 'Cập nhật tier thành công',
      tierDeleted: 'Xóa tier thành công',
      feeCreated: 'Tạo phí thành công',
      feeUpdated: 'Cập nhật phí thành công',
      feeDeleted: 'Xóa phí thành công',
      failedLoad: 'Không thể tải tiers',
      failedCreateTier: 'Không thể tạo tier',
      failedUpdateTier: 'Không thể cập nhật tier',
      failedDeleteTier: 'Không thể xóa tier',
      failedSaveFee: 'Không thể lưu phí',
      failedDeleteFee: 'Không thể xóa phí',
    },
    dashboardPage: {
      title: 'Bảng điều khiển',
      subtitle: 'Tổng quan về đơn hàng, doanh thu, tồn kho và hoạt động gần đây của hệ thống.',
      loading: 'Đang tải bảng điều khiển...',
      failedLoad: 'Không thể tải dữ liệu thống kê bảng điều khiển',
      timeRangeLabel: 'Khoảng thời gian',
      today: 'Hôm nay',
      yesterday: 'Hôm qua',
      last7Days: '7N',
      last30Days: '30N',
      last90Days: '90N',
      lastYear: '1N',
      sellerScope: 'Chế độ seller',
      sellerScopeDescription: 'Thống kê đang được giới hạn theo dữ liệu của riêng cửa hàng bạn.',
      totalOrders: 'Đơn hàng',
      totalRevenue: 'Doanh thu',
      productsVariants: 'Sản phẩm',
      totalStock: 'Tồn kho',
      ordersThisPeriod: '{count} đơn trong giai đoạn này',
      revenueThisPeriod: '{amount} trong giai đoạn này',
      variants: '{count} biến thể · {active} đang hoạt động',
      lowStockWarning: '{count} biến thể sắp hết hàng',
      totalDeposits: 'Nạp tiền',
      totalWithdrawals: 'Rút tiền',
      totalPayments: 'Thanh toán',
      pendingTransactions: 'Đang chờ',
      transactionsThisPeriod: '{count} giao dịch trong giai đoạn này',
      productSalesQuantity: 'Số lượng bán theo sản phẩm',
      top5Products: 'Hiệu suất nhóm sản phẩm nổi bật theo thời gian',
      revenueByPaymentStatus: 'Doanh thu theo trạng thái thanh toán',
      dailyBreakdown: 'Phân rã doanh thu theo ngày',
      dailyOrders: 'Đơn hàng theo ngày',
      ordersPerDay: 'Số đơn được tạo mỗi ngày',
      transactionsOverview: 'Tổng quan giao dịch',
      dailyTransactions: 'Giá trị giao dịch theo từng loại mỗi ngày',
      noSalesData: 'Chưa có dữ liệu bán hàng theo sản phẩm',
      noRevenueData: 'Chưa có dữ liệu doanh thu',
      noOrderData: 'Chưa có dữ liệu đơn hàng theo ngày',
      noTransactionData: 'Chưa có dữ liệu giao dịch',
      ordersByPaymentStatus: 'Đơn hàng theo trạng thái thanh toán',
      ordersByFulfillStatus: 'Đơn hàng theo trạng thái xử lý',
      topProducts: 'Sản phẩm nổi bật',
      recentOrders: 'Đơn hàng gần đây',
      noRecentOrders: 'Chưa có đơn hàng gần đây',
      noTopProducts: 'Chưa có sản phẩm nổi bật',
      orderId: 'Mã đơn',
      store: 'Cửa hàng',
      items: 'Số món',
      paymentStatus: 'Thanh toán',
      fulfillStatus: 'Xử lý',
      created: 'Tạo lúc',
      viewAll: 'Xem tất cả',
      vsPrevious: 'so với kỳ trước',
      empty: 'Không có dữ liệu',
      units: 'sản phẩm',
      // Orders compact card rows
      ordersTotalRow: 'Tổng đơn',
      ordersShippingRow: 'Đang giao',
      ordersDeliveredRow: 'Hoàn thành',
      ordersOnHoldRow: 'Tạm hoãn',
      // Revenue card rows
      revenueTotalRow: 'Tổng doanh thu',
      revenuePeriodRow: 'Trong kỳ',
      revenuePaidRow: 'Đã thanh toán',
      revenuePendingRow: 'Chờ duyệt',
      // Products & stock card
      productsStockTitle: 'Sản phẩm & Kho',
      productsRow: 'Sản phẩm',
      variantsRow: 'Biến thể',
      stockRow: 'Tồn kho',
      lowStockRow: 'Sắp hết hàng',
      // Financials card
      financialsTitle: 'Tài chính',
      depositsRow: 'Nạp tiền',
      withdrawalsRow: 'Rút tiền',
      paymentsRow: 'Thanh toán',
      txPeriodRow: 'Giao dịch trong kỳ',
      // Status breakdown
      paymentBreakdownTitle: 'Đơn theo thanh toán',
      ordersUnit: 'đơn',
      // Ranking tables
      rankingProductsTitle: 'Xếp hạng sản phẩm',
      rankingSellersTitle: 'Xếp hạng Seller',
      rankingUpdated: 'Cập nhật:',
      rankCol: 'Xếp hạng',
      productNameCol: 'Tên sản phẩm',
      soldQtyCol: 'Số lượng bán',
      sellerNameCol: 'Tên seller',
      totalItemsCol: 'Tổng items',
      noSellerData: 'Chưa có dữ liệu seller',
      // Funnel
      funnelCellSize: '1 ô = {size} đơn',
      // Production flow labels
      flowNewOrder: 'New Order',
      flowConfirmed: 'Confirmed',
      flowProducing: 'Producing',
      flowShipped: 'Shipped',
      // Shop stats table
      shopStatsTitle: 'Thống kê đơn hàng theo shop',
      shopColIndex: '#',
      shopColName: 'Tên shop',
      shopColTotal: 'Tổng đơn hàng',
      shopColRefund: 'Đơn hoàn',
      shopColPaid: 'Đã thanh toán',
      shopColProcessing: 'Đang xử lý',
      shopColOnHold: 'On hold',
      shopColSellers: 'Số seller',
      noShopData: 'Chưa có dữ liệu',
    },
    staffReportPage: {
      title: 'Báo cáo hiệu suất nhân sự',
      subtitle: 'Theo dõi hiệu suất xử lý công việc của nhân sự',
      filters: {
        dateFrom: 'Từ ngày',
        dateTo: 'Đến ngày',
        staffMember: 'Nhân sự',
        allStaff: 'Tất cả nhân sự',
        apply: 'Áp dụng bộ lọc',
        refresh: 'Làm mới dữ liệu',
      },
      summary: {
        title: 'Tổng quan hiệu suất nhân sự',
        staffName: 'Tên nhân sự',
        username: 'Username',
        itemsProcessed: 'Số mục đã xử lý',
        contribution: 'Tỷ lệ đóng góp',
        share: 'Tỷ trọng',
        noData: 'Không có dữ liệu hiệu suất trong khoảng thời gian đã chọn.',
        total: 'Tổng',
        items: 'mục',
      },
      details: {
        title: 'Chi tiết hoạt động xử lý',
        staffName: 'Tên nhân sự',
        username: 'Username',
        orderItem: 'Đơn hàng / Item',
        order: 'Đơn',
        item: 'Item',
        metaKey: 'Meta Key',
        processedAt: 'Thời gian xử lý',
        noData: 'Không có dữ liệu hoạt động.',
      },
      loading: 'Đang tải dữ liệu báo cáo...',
      failedLoadList: 'Không thể tải danh sách nhân sự',
      failedLoadReport: 'Không thể tải dữ liệu báo cáo',
    },
    attendancesPage: {
      title: 'Quản lý chấm công',
      subtitle: 'Theo dõi giờ làm việc và log chấm công của nhân viên',
      importBtn: 'Import file .txt',
      importing: 'Đang import...',
      filters: {
        employeeName: 'Tên nhân viên',
        searchPlaceholder: 'Tìm theo tên...',
        customRange: 'Khoảng tùy chỉnh',
        from: 'Từ',
        to: 'Đến',
        date: 'Ngày cụ thể',
        month: 'Tháng',
        clear: 'Xóa bộ lọc',
      },
      columns: {
        id: 'ID',
        employeeName: 'Tên nhân viên',
        totalDays: 'Tổng ngày',
        week: 'Tuần',
        month: 'Tháng',
        year: 'Năm',
      },
      days: 'ngày',
      logs: {
        show: 'Hiển thị',
        entries: 'dòng',
        showing: 'Đang hiển thị',
        of: 'trên',
        records: 'bản ghi',
        noRecords: 'Không có bản ghi',
        date: 'Ngày',
        checkIn: 'Check In',
        checkOut: 'Check Out',
        totalWork: 'Tổng giờ',
        loading: 'Đang tải...',
        noRecordsFound: 'Không tìm thấy bản ghi',
        completeMissing: 'Cập nhật',
        previous: 'Trước',
        next: 'Sau',
        pageOf: 'Trang {current} / {total}',
      },
      editModal: {
        title: 'Hoàn thiện log chấm công thiếu',
        employee: 'Nhân viên',
        workDate: 'Ngày làm việc',
        existingTime: 'Thời gian hiện có',
        missingType: 'Loại thiếu',
        checkIn: 'Check In',
        checkOut: 'Check Out',
        time: 'Thời gian',
        cancel: 'Hủy',
        save: 'Lưu',
        saving: 'Đang lưu...',
        validation: {
          timeRequired: 'Vui lòng chọn thời gian',
        },
      },
      messages: {
        failedLoadData: 'Không thể tải dữ liệu chấm công',
        failedLoadLogs: 'Không thể tải log người dùng',
        importSuccess: 'Import thành công',
        importFailed: 'Import thất bại',
        noRecords: 'Không có dữ liệu chấm công.',
        updateSuccess: 'Cập nhật chấm công thành công',
        updateFailed: 'Cập nhật chấm công thất bại',
      },
    },
    payrollPage: {
      title: 'Báo cáo lương',
      subtitle: 'Theo dõi bảng lương cho {period} với {count} nhân sự',
      setRate: 'Thiết lập lương',
      rewardsPenalties: 'Thưởng / Phạt',
      month: 'Tháng',
      customRange: 'Khoảng tùy chỉnh',
      from: 'Từ',
      to: 'Đến',
      totalHours: 'Tổng giờ',
      totalSalary: 'Tổng lương',
      netTotal: 'Lương net',
      companyTaxTotal: 'Thuế công ty',
      missingRate: 'Thiếu mức lương',
      staffs: 'nhân sự',
      noEmployees: 'Không có nhân viên nào',
      employee: 'Nhân viên',
      rateHr: 'Rate/Hr',
      hours: 'Giờ',
      adjustments: 'Điều chỉnh',
      grossSalary: 'Gross',
      netSalary: 'Net',
      companyTax: 'Thuế Cty',
      totalSalaryCol: 'Tổng',
      actions: 'Thao tác',
      edit: 'Sửa',
      log: 'Log',
      view: 'Xem',
      clickToEdit: 'Bấm để sửa',
      save: 'Lưu',
      cancel: 'Hủy',
      close: 'Đóng',
      loading: 'Đang tải bảng lương...',
      selectEmployee: 'Vui lòng chọn ít nhất một nhân viên',
      selectTierOrRate: 'Vui lòng chọn tier hoặc nhập mức lương tùy chỉnh',
      fillTypeAmount: 'Vui lòng nhập loại và số tiền',
      rateSetSuccess: 'Thiết lập thành công {success}/{total} mức lương',
      failedSetRate: 'Thiết lập mức lương thất bại',
      rateUpdated: 'Cập nhật mức lương thành công',
      failedUpdateRate: 'Cập nhật mức lương thất bại',
      adjustmentSuccess: 'Tạo thành công {success}/{total} điều chỉnh',
      failedAdjustment: 'Tạo điều chỉnh thất bại',
      failedLoadPayroll: 'Không thể tải dữ liệu bảng lương',
      fieldUpdated: 'Cập nhật thành công',
      failedUpdate: 'Cập nhật thất bại',
      setRateModal: {
        title: 'Thiết lập mức lương',
        selectEmployees: 'Chọn nhân viên',
        selectAll: 'Chọn tất cả',
        selected: 'đã chọn',
        selectTier: 'Chọn tier',
        or: 'HOẶC',
        customRate: 'Mức lương giờ tùy chỉnh',
        effectiveFrom: 'Hiệu lực từ',
        setting: 'Đang thiết lập...',
        setRateBtn: 'Thiết lập',
      },
      editRateModal: {
        title: 'Chỉnh sửa mức lương',
        hourlyRate: 'Mức lương giờ',
        detachNote: 'Nhập mức lương tùy chỉnh sẽ tách nhân viên khỏi tier hiện tại.',
        note: 'Ghi chú',
        reasonPlaceholder: 'Lý do cập nhật lương',
        saving: 'Đang lưu...',
      },
      salaryLog: {
        title: 'Lịch sử lương',
        noHistory: 'Không có lịch sử lương',
        custom: 'Tùy chỉnh',
        from: 'Từ',
        ended: 'Kết thúc',
        current: 'Hiện tại',
      },
      adjustmentModal: {
        title: 'Thêm thưởng / phạt',
        type: 'Loại',
        typePlaceholder: 'Ví dụ: Bonus, phạt đi trễ...',
        amount: 'Số tiền',
        action: 'Hành động',
        addReward: 'Thêm thưởng',
        deductPenalty: 'Trừ phạt',
        date: 'Ngày',
        processing: 'Đang xử lý...',
        add: 'Thêm',
        deduct: 'Trừ',
      },
      adjustmentDetail: {
        title: 'Chi tiết điều chỉnh',
        noAdjustments: 'Không có điều chỉnh nào',
        typeReason: 'Loại / Lý do',
      },
      guide: {
        title: 'Hướng dẫn bảng lương',
        close: 'Đóng',
        steps: [
          {
            icon: '📊',
            title: 'Kiểm tra giờ làm',
            desc: 'Xem bảng lương theo tháng hoặc theo khoảng thời gian trước khi chốt lương.',
          },
          {
            icon: '💰',
            title: 'Thiết lập mức lương',
            desc: 'Gán mức lương giờ bằng tier hoặc mức lương tùy chỉnh cho nhân viên đã chọn.',
          },
          {
            icon: '⚖️',
            title: 'Áp dụng thưởng phạt',
            desc: 'Dùng adjustment để cộng thưởng hoặc trừ phạt vào bảng lương.',
          },
          {
            icon: '📈',
            title: 'Hoàn thiện lương net',
            desc: 'Sửa trực tiếp net salary và company tax để ra tổng lương cuối cùng.',
          },
        ],
      },
    },
    payrollTiersPage: {
      title: 'Bậc lương',
      subtitle: 'Quản lý các bậc lương cho payroll',
      createTier: 'Tạo bậc lương',
      tierName: 'Tên bậc lương',
      hourlyRate: 'Lương theo giờ',
      currency: 'Tiền tệ',
      description: 'Mô tả',
      actions: 'Thao tác',
      noTiers: 'Chưa có bậc lương nào',
      createTitle: 'Tạo bậc lương',
      editTitle: 'Chỉnh sửa bậc lương',
      deleteTitle: 'Xóa bậc lương',
      namePlaceholder: 'Nhập tên bậc lương',
      ratePlaceholder: '15.00',
      descriptionPlaceholder: 'Ghi chú tùy chọn cho bậc lương',
      create: 'Tạo',
      creating: 'Đang tạo...',
      save: 'Lưu',
      saving: 'Đang lưu...',
      cancel: 'Hủy',
      delete: 'Xóa',
      deleting: 'Đang xóa...',
      confirmDelete: 'Bạn có chắc muốn xóa bậc lương này?',
      fillTypeAmount: 'Vui lòng nhập tên bậc lương và lương theo giờ',
      tierCreated: 'Tạo bậc lương thành công',
      tierUpdated: 'Cập nhật bậc lương thành công',
      tierDeleted: 'Xóa bậc lương thành công',
      failedLoadTiers: 'Không thể tải bậc lương',
      failedCreateTier: 'Tạo bậc lương thất bại',
      failedUpdateTier: 'Cập nhật bậc lương thất bại',
      failedDeleteTier: 'Xóa bậc lương thất bại',
    },
    ticketsPage: {
      title: 'Khiếu nại hỗ trợ',
      subtitle: 'Quản lý ticket hỗ trợ',
      totalTickets: 'ticket',
      tabs: {
        all: 'Tất cả ticket',
        new: 'Mới',
        solved: 'Đã xử lý',
      },
      filters: {
        ticketId: 'Ticket ID',
        orderId: 'Order ID',
        subject: 'Tiêu đề',
        allSellers: 'Tất cả seller',
        allSupport: 'Tất cả support',
      },
      columns: {
        id: 'ID',
        orderId: 'Order ID',
        subject: 'Tiêu đề',
        status: 'Trạng thái',
        userReply: 'Người phản hồi',
        lastReply: 'Phản hồi gần nhất',
        owner: 'Phụ trách',
        updated: 'Cập nhật',
        actions: 'Thao tác',
      },
      status: {
        new: 'Mới',
        solved: 'Đã xử lý',
      },
      actions: {
        view: 'Xem',
        solve: 'Xử lý',
      },
      noTicketsTitle: 'Không tìm thấy ticket',
      noTicketsDescriptionFiltered: 'Thử điều chỉnh bộ lọc của bạn',
      noTicketsDescriptionEmpty: 'Hiện chưa có ticket nào',
      loadFailed: 'Không thể tải danh sách ticket',
      statusUpdated: 'Cập nhật trạng thái ticket thành công!',
      statusUpdateFailed: 'Cập nhật trạng thái ticket thất bại',
      createSuccess: 'Tạo ticket hỗ trợ thành công!',
      createDialog: {
        createTitle: 'Tạo ticket hỗ trợ',
        subject: 'Tiêu đề',
        subjectPlaceholder: 'Mô tả ngắn về vấn đề',
        message: 'Nội dung',
        messagePlaceholder: 'Mô tả chi tiết vấn đề...',
        attachFile: 'Đính kèm file (Tùy chọn)',
        clickToUpload: 'Bấm để tải file lên',
        fileHint: 'JPG, PNG, GIF, PDF (tối đa 10MB)',
        cancel: 'Hủy',
        creating: 'Đang tạo...',
        createNew: 'Tạo ticket',
        subjectRequired: 'Tiêu đề là bắt buộc',
        messageRequired: 'Nội dung là bắt buộc',
        orderIdMissing: 'Thiếu Order ID. Vui lòng thử lại.',
        fileSizeError: 'Kích thước file phải nhỏ hơn 10MB',
        fileTypeError: 'Chỉ chấp nhận JPG, PNG, GIF và PDF',
        createFailed: 'Tạo ticket thất bại. Vui lòng thử lại.',
      },
    },
    ticketDetailPage: {
      back: 'Quay lại',
      backToTickets: 'Về danh sách ticket',
      loading: 'Đang tải ticket...',
      notFound: 'Không tìm thấy ticket',
      loadDetailFailed: 'Không thể tải chi tiết ticket',
      fileSizeError: 'Kích thước file phải nhỏ hơn 10MB',
      fileTypeError: 'Chỉ chấp nhận JPG, PNG, GIF và PDF',
      viewPdf: 'Xem PDF',
      noMessages: 'Chưa có tin nhắn nào. Hãy bắt đầu cuộc trò chuyện!',
      placeholder: 'Nhập tin nhắn... (Shift+Enter để xuống dòng)',
      placeholderImage: 'Đã chọn ảnh - sẵn sàng gửi',
      enterMessage: 'Vui lòng nhập tin nhắn hoặc đính kèm file',
      sendFailed: 'Gửi tin nhắn thất bại',
      statusUpdated: 'Cập nhật trạng thái thành công!',
      statusUpdateFailed: 'Cập nhật trạng thái thất bại',
      markSolved: 'Đánh dấu đã xử lý',
      reopen: 'Mở lại',
      remove: 'Xóa',
      status: {
        new: 'Mới',
        solved: 'Đã xử lý',
      },
      unknown: 'Không rõ',
    },
    walletTransactionsPage: {
      title: 'Giao dịch ví',
      subtitle: 'Lịch sử giao dịch',
      totalTransactions: 'giao dịch',
      exportAll: 'Xuất tất cả',
      exportPayments: 'Xuất thanh toán',
      exportDeposits: 'Xuất nạp tiền',
      exportRefunds: 'Xuất hoàn tiền',
      tabs: {
        all: 'Tất cả giao dịch',
        payments: 'Thanh toán (Trừ tiền)',
        deposits: 'Nạp tiền (Cộng tiền)',
        refunds: 'Hoàn tiền',
      },
      filters: {
        allSellers: 'Tất cả seller',
        fromDate: 'Từ ngày',
        toDate: 'Đến ngày',
        search: 'Tìm kiếm...',
      },
      columns: {
        id: 'ID',
        transactionId: 'Mã giao dịch',
        seller: 'Seller',
        orderId: 'Order ID',
        store: 'Store',
        type: 'Loại',
        amount: 'Số tiền',
        balance: 'Số dư',
        note: 'Ghi chú',
        status: 'Trạng thái',
        date: 'Ngày tạo',
      },
      status: {
        completed: 'Hoàn tất',
        pending: 'Đang chờ',
        failed: 'Thất bại',
      },
      type: {
        add_fund: 'Nạp tiền',
        order_payment: 'Thanh toán đơn',
        refund: 'Hoàn tiền',
      },
      summary: {
        total: 'Tổng',
        page: 'Trang này',
      },
      loading: 'Đang tải giao dịch...',
      noTransactionsTitle: 'Không tìm thấy giao dịch',
      noTransactionsDescriptionFiltered: 'Thử điều chỉnh bộ lọc của bạn',
      noTransactionsDescriptionEmpty: 'Chưa có giao dịch nào',
      loadFailed: 'Không thể tải giao dịch',
      loadSellersFailed: 'Không thể tải seller',
      exporting: 'Đang xuất giao dịch...',
      exportSuccess: 'Xuất giao dịch thành công!',
      exportFailed: 'Xuất giao dịch thất bại',
      na: 'N/A',
      none: 'Không có nội dung',
    },
    pendingFundPage: {
      title: 'Tiền chờ duyệt',
      subtitle: 'Duyệt yêu cầu nạp tiền từ seller',
      showing: 'Hiển thị {count} yêu cầu chờ duyệt',
      loading: 'Đang tải...',
      noRequests: 'Không có yêu cầu chờ duyệt',
      allCaught: 'Tất cả yêu cầu nạp tiền đã được xử lý.',
      fetchError: 'Không thể tải yêu cầu chờ duyệt',
      confirmApprove: 'Bạn có chắc muốn duyệt yêu cầu nạp tiền này không?',
      approveSuccess: 'Duyệt yêu cầu nạp tiền thành công!',
      approveFailed: 'Duyệt yêu cầu thất bại',
      rejectSuccess: 'Đã từ chối yêu cầu nạp tiền',
      rejectFailed: 'Từ chối yêu cầu thất bại',
      approve: 'Duyệt',
      reject: 'Từ chối',
      columns: {
        id: 'ID',
        seller: 'Seller',
        type: 'Loại',
        amount: 'Số tiền',
        transactionId: 'Mã giao dịch',
        note: 'Ghi chú',
        date: 'Ngày tạo',
        actions: 'Thao tác',
      },
      rejectModal: {
        title: 'Từ chối yêu cầu nạp tiền',
        subtitle: 'Vui lòng nhập lý do từ chối (không bắt buộc)',
        placeholder: 'Nhập lý do từ chối...',
        cancel: 'Hủy',
        confirm: 'Xác nhận từ chối',
      },
      type: {
        deposit: 'Nạp tiền',
        refund: 'Hoàn tiền',
      },
      na: 'N/A',
    },
    partnerAppsPage: {
      title: 'Ứng dụng đối tác',
      subtitle: 'Sao chép link xác thực và quản lý cấu hình kết nối ứng dụng đối tác.',
      addApp: 'Thêm ứng dụng đối tác',
      loading: 'Đang tải ứng dụng đối tác...',
      empty: 'Không tìm thấy ứng dụng đối tác',
      copied: 'Đã sao chép link xác thực',
      noAuthLink: 'Ứng dụng đối tác này chưa có link xác thực',
      na: 'N/A',
      columns: {
        name: 'Tên',
        linkAuth: 'Link Auth',
        proxyStatus: 'Trạng thái proxy',
        status: 'Trạng thái',
        actions: 'Thao tác',
      },
      copyLink: 'Sao chép link auth',
      edit: 'Sửa',
      dialog: {
        createTitle: 'Tạo ứng dụng đối tác',
        editTitle: 'Chỉnh sửa ứng dụng đối tác',
        name: 'Tên',
        slug: 'Slug',
        authUrl: 'URL xác thực',
        proxyStatus: 'Trạng thái proxy',
        status: 'Trạng thái',
        cancel: 'Hủy',
        create: 'Tạo',
        update: 'Cập nhật',
        successCreate: 'Tạo ứng dụng đối tác thành công',
        successUpdate: 'Cập nhật ứng dụng đối tác thành công',
      },
    },
    partnerStoresPage: {
      title: 'Cửa hàng đối tác',
      subtitle: 'Tạo và quản lý các shop đối tác tách biệt với cửa hàng legacy.',
      addStore: 'Thêm cửa hàng đối tác',
      searchPlaceholder: 'Tìm theo tên, mã, user, tài khoản...',
      loading: 'Đang tải cửa hàng đối tác...',
      empty: 'Không tìm thấy cửa hàng đối tác',
      failed: 'Tải cửa hàng đối tác thất bại',
      syncTitle: 'Đồng bộ đơn hàng',
      syncDescription: 'Xác nhận đồng bộ các đơn hàng mới nhất từ shop đối tác này.',
      syncConfirm: 'Bắt đầu đồng bộ',
      syncCancel: 'Hủy',
      syncProgressTitle: 'Đang đồng bộ đơn hàng...',
      syncProgressDescription: 'Vui lòng chờ trong lúc hệ thống xử lý đơn hàng đối tác.',
      syncDone: 'Đồng bộ đơn hàng thành công',
      na: 'N/A',
      columns: {
        id: 'ID',
        partner: 'Đối tác',
        name: 'Tên',
        user: 'User',
        status: 'Trạng thái',
        totalOrders: 'Tổng đơn hàng',
        accountNo: 'Số tài khoản',
        actions: 'Thao tác',
      },
      dialog: {
        createTitle: 'Thêm cửa hàng đối tác',
        editTitle: 'Chỉnh sửa cửa hàng đối tác',
        storeName: 'Tên cửa hàng',
        storeCode: 'Mã shop',
        user: 'Staff',
        partnerApp: 'Ứng dụng đối tác',
        status: 'Trạng thái',
        accountNo: 'Số tài khoản',
        cancel: 'Hủy',
        create: 'Gửi',
        update: 'Cập nhật',
        successCreate: 'Tạo cửa hàng đối tác thành công',
        successUpdate: 'Cập nhật cửa hàng đối tác thành công',
        na: 'N/A',
      },
    },
    partnerSyncOrdersPage: {
      title: 'Đơn hàng đã đồng bộ',
      subtitle: 'Xem các đơn hàng đối tác mới đồng bộ trước khi chuyển vào luồng chính.',
      loading: 'Đang tải đơn hàng đã đồng bộ...',
      empty: 'Chưa có đơn hàng đồng bộ. Hãy chạy sync từ Partner Stores trước.',
      filters: {
        store: 'Cửa hàng',
        orderNo: 'Mã đơn đối tác',
        status: 'Trạng thái',
        fulfillment: 'Fulfillment',
        allStores: 'Tất cả cửa hàng',
        allStatuses: 'Tất cả trạng thái',
        allFulfillment: 'Tất cả fulfillment',
        orderNoPlaceholder: 'Tìm mã đơn đối tác...',
        search: 'Tìm kiếm',
        clearAll: 'Xóa bộ lọc',
        pending: 'Chờ xử lý',
        paid: 'Đã thanh toán',
        cancelled: 'Đã hủy',
        noFulfillment: 'Chưa fulfillment',
        ready: 'Sẵn sàng',
        shipped: 'Đã giao',
      },
      columns: {
        id: 'ID',
        store: 'Cửa hàng',
        customer: 'Khách hàng',
        user: 'User',
        partnerOrder: 'Mã đơn TikTok',
        tracking: 'Tracking',
        items: 'Sản phẩm',
        discount: 'Giảm giá',
        total: 'Tổng',
        status: 'Trạng thái',
        fulfillment: 'Fulfillment',
        note: 'Ghi chú',
        actions: 'Thao tác',
      },
      labels: {
        sku: 'SKU',
        qty: 'SL',
        buyLabel: 'Tạo vận chuyển',
        buyLabels: 'Tạo vận chuyển',
        edit: 'Sửa',
        ship: 'Giao',
        delete: 'Xóa',
      },
    },
    stock: {
      manage: {
        title: 'Quản lý tồn kho',
        description: 'Giữ nguyên logic màn cũ trên giao diện hệ thống mới.',
        importExport: 'Nhập/Xuất',
        loading: 'Đang tải dữ liệu tồn kho...',
        loadError: 'Không thể tải dữ liệu tồn kho',
        summary: {
          totalStock: 'Tổng tồn kho',
          reserved: 'Đang giữ',
          available: 'Có sẵn',
          lowStockItems: 'Sản phẩm sắp hết',
        },
        filters: {
          variantId: 'ID biến thể',
          sku: 'SKU',
          style: 'Style',
          color: 'Màu sắc',
          size: 'Kích thước',
          stockLevel: 'Mức tồn kho',
          status: 'Trạng thái',
          searchPlaceholder: 'Tìm kiếm...',
          allStyles: 'Tất cả style',
          allColors: 'Tất cả màu',
          allSizes: 'Tất cả kích thước',
          all: 'Tất cả',
          lowStock: 'Sắp hết (< 5)',
          outOfStock: 'Hết hàng',
          active: 'Hoạt động',
          inactive: 'Không hoạt động',
          reset: 'Đặt lại',
        },
        empty: {
          title: 'Không tìm thấy sản phẩm',
          description: 'Thử điều chỉnh bộ lọc của bạn',
        },
        tabs: {
          variants: 'biến thể',
        },
        bulk: {
          selected: 'Đã chọn {count} biến thể',
          hint: 'Chọn thao tác để áp dụng cho tất cả biến thể đã chọn',
          clearSelection: 'Xóa chọn',
          operation: 'Thao tác',
          selectOperation: 'Chọn thao tác...',
          stockOperations: 'Thao tác tồn kho',
          statusOperations: 'Thao tác trạng thái',
          addStock: 'Thêm vào tồn kho hiện tại',
          subtractStock: 'Trừ khỏi tồn kho hiện tại',
          setStock: 'Đặt mức tồn kho',
          activate: 'Kích hoạt',
          deactivate: 'Hủy kích hoạt',
          amountToAdd: 'Số lượng thêm',
          amountToSubtract: 'Số lượng trừ',
          newStockLevel: 'Mức tồn kho mới',
          enterValue: 'Nhập giá trị...',
          reason: 'Lý do (Tùy chọn)',
          reasonPlaceholder: 'Ví dụ: Hàng mới về...',
          applyTo: 'Áp dụng cho {count} biến thể',
          selectVariantsAndAction: 'Vui lòng chọn biến thể và thao tác',
          enterValidStock:
            'Vui lòng nhập giá trị tồn kho hợp lệ (0 hoặc lớn hơn)',
          success: 'Cập nhật thành công {count} biến thể',
        },
        table: {
          variantId: 'ID biến thể',
          sku: 'SKU',
          style: 'Style',
          color: 'Màu sắc',
          size: 'Kích thước',
          stock: 'Tồn kho',
          reserved: 'Đang giữ',
          available: 'Có sẵn',
          active: 'Hoạt động',
          actions: 'Thao tác',
          save: 'Lưu',
          cancel: 'Hủy',
          edit: 'Sửa',
          history: 'Lịch sử',
          noVariants: 'Không tìm thấy biến thể cho sản phẩm này',
          stockCannotBeNegative: 'Tồn kho không thể âm',
          noChangesToSave: 'Không có thay đổi nào để lưu',
          variantUpdated: 'Cập nhật biến thể thành công',
          updateFailed: 'Cập nhật biến thể thất bại',
          variantStatusUpdated: 'Trạng thái biến thể đã được cập nhật',
        },
        historyDialog: {
          title: 'Lịch sử tồn kho',
          currentStock: 'Tồn kho hiện tại',
          loading: 'Đang tải lịch sử...',
          noRecords: 'Không tìm thấy lịch sử',
          increase: 'Tăng',
          decrease: 'Giảm',
          adjust: 'Điều chỉnh',
          import: 'Nhập',
          skuUpdated: 'Cập nhật SKU',
          styleUpdated: 'Cập nhật style',
          activated: 'Đã kích hoạt',
          deactivated: 'Đã hủy kích hoạt',
          bulkUpdate: 'Cập nhật hàng loạt',
          bulkOperation: 'Thao tác hàng loạt',
          operation: 'Thao tác',
          showingLast: 'Hiển thị 20 thay đổi gần nhất',
          sku: 'SKU',
          style: 'STYLE',
          active: 'ACTIVE',
          empty: '(trống)',
          variantId: 'ID biến thể',
        },
        importExportDialog: {
          title: 'Nhập/Xuất tồn kho',
          import: 'Nhập',
          export: 'Xuất',
          importInstructions: 'Hướng dẫn nhập:',
          instructionFile: 'Tệp phải có định dạng CSV',
          instructionId:
            'Bắt buộc: Ít nhất một mã định danh (ID biến thể hoặc SKU)',
          instructionFields:
            'Các trường tùy chọn: Tồn kho, Style, Màu sắc, Kích thước, Sản phẩm',
          instructionUpdate: 'Chỉ các trường có trong CSV mới được cập nhật',
          stockOperationType: 'Loại thao tác tồn kho',
          setStock: 'Đặt tồn kho (Thay thế)',
          addStock: 'Thêm tồn kho (Tăng)',
          subtractStock: 'Trừ tồn kho (Giảm)',
          hintSet: 'Thay thế tồn kho hiện tại bằng giá trị từ tệp',
          hintAdd: 'Thêm giá trị từ tệp vào tồn kho hiện tại',
          hintSubtract: 'Trừ giá trị từ tệp khỏi tồn kho hiện tại',
          selectCsvFile: 'Chọn tệp CSV',
          chooseFile: 'Chọn tệp...',
          downloadTemplate: 'Tải mẫu',
          skuImport: 'Nhập theo SKU',
          variantImport: 'Nhập theo biến thể',
          fullImport: 'Nhập đầy đủ',
          skuTemplateHint: 'Tải mẫu SKU (SKU, Tồn kho)',
          variantTemplateHint: 'Tải mẫu biến thể (ID biến thể, Tồn kho)',
          fullTemplateHint: 'Tải mẫu đầy đủ (Tất cả các trường)',
          importing: 'Đang nhập...',
          importBtn: 'Nhập',
          importResults: 'Kết quả nhập',
          success: 'Thành công:',
          failed: 'Thất bại:',
          errors: 'Lỗi:',
          moreErrors: '... và {count} lỗi khác',
          exportStockData: 'Xuất dữ liệu tồn kho:',
          exportDesc: 'Xuất tất cả dữ liệu tồn kho ra tệp CSV bao gồm:',
          exportFields1: 'ID biến thể, SKU, Tên sản phẩm',
          exportFields2: 'Style, Màu sắc, Kích thước',
          exportFields3: 'Tồn kho, Đang giữ, Có sẵn',
          exportFields4: 'Trạng thái (Hoạt động/Không hoạt động)',
          exportPreview1:
            'Tệp xuất sẽ bao gồm tất cả các biến thể với thông tin tồn kho hiện tại.',
          exportPreview2:
            'Thời gian xuất phụ thuộc vào số lượng biến thể trong kho của bạn.',
          exporting: 'Đang xuất...',
          exportToCsv: 'Xuất ra CSV',
          pleaseSelectCsv: 'Vui lòng chọn tệp CSV',
          pleaseSelectFile: 'Vui lòng chọn tệp',
          importSuccess: 'Nhập dữ liệu thành công',
          importFailed: 'Nhập dữ liệu thất bại',
          failedToImport: 'Nhập tồn kho thất bại',
          exportSuccess: 'Xuất dữ liệu thành công',
          exportFailed: 'Xuất dữ liệu thất bại',
          failedToExport: 'Xuất tồn kho thất bại',
        },
      },
      shortage: {
        title: 'Báo cáo thiếu hàng',
        subtitleWithCount: '{count} đơn hàng đang chờ xử lý',
        subtitleAllGood: 'Không có đơn hàng chờ',
        viewByVariant: 'Xem theo biến thể',
        exportCsv: 'Xuất CSV',
        exporting: 'Đang xuất báo cáo thiếu hàng...',
        exportSuccess: 'Xuất báo cáo thành công',
        exportFailed: 'Xuất báo cáo thất bại',
        failedToLoadReport: 'Tải báo cáo thiếu hàng thất bại',
        loading: 'Đang tải đơn hàng chờ...',
        noPendingOrders: 'Không tìm thấy đơn hàng chờ',
        totalPendingOrders: 'Tổng đơn chờ',
        ordersWithShortage: 'Đơn thiếu hàng',
        variantsAffected: 'Biến thể ảnh hưởng',
        totalShortage: 'Tổng thiếu hụt',
        searchOrder: 'Tìm đơn hàng',
        orderIdRefIdPlaceholder: 'Mã đơn / Ref ID...',
        searchVariant: 'Tìm biến thể',
        variantIdPlaceholder: 'Mã biến thể...',
        pendingReason: 'Lý do chờ',
        fromDate: 'Từ ngày',
        toDate: 'Đến ngày',
        sortBy: 'Sắp xếp theo',
        orderId: 'Mã đơn',
        refId: 'Ref ID',
        seller: 'Người bán',
        items: 'Số item',
        shortage: 'Thiếu hụt',
        daysPending: 'Số ngày chờ',
        action: 'Thao tác',
        view: 'Xem',
        day: 'ngày',
        days: 'ngày',
        awaitingProcessing: 'Đang chờ xử lý',
        awaitingProcessingDesc:
          'Tồn kho đang có sẵn cho đơn này. Hệ thống sẽ sớm phân bổ tồn kho.',
        missingFiles: 'Thiếu file',
        noItems: 'Không có sản phẩm',
        noItemsDesc: 'Đơn hàng này không có sản phẩm nào. Vui lòng kiểm tra lại.',
        unknownReason: 'Lý do không xác định',
        unknownReasonDesc:
          'Đơn hàng này đang chờ vì lý do chưa xác định hoặc đang bị giữ thủ công.',
        status: {
          shortage: 'Thiếu tồn kho',
          missing_files: 'Thiếu file',
          awaiting_allocation: 'Chờ phân bổ',
          no_items: 'Không có sản phẩm',
          unknown: 'Không xác định',
        },
        sortOptions: {
          seller_username: 'Người bán',
          days_pending: 'Số ngày chờ',
          shortage: 'Mức thiếu hụt',
          created_at: 'Ngày tạo',
        },
        variantTable: {
          title: 'Biến thể thiếu hàng',
          noVariants: 'Không có biến thể thiếu hàng',
          variantId: 'Mã biến thể',
          style: 'Style',
          color: 'Màu',
          size: 'Size',
          stock: 'Tồn kho',
          demand: 'Nhu cầu',
          shortage: 'Thiếu hụt',
        },
      },
      shortageByVariant: {
        title: 'Thiếu hàng theo biến thể',
        subtitleWithCount: '{count} biến thể đang thiếu hàng',
        subtitleAllGood: 'Không có biến thể thiếu hàng',
        viewByOrder: 'Xem theo đơn hàng',
        failedToLoad: 'Tải báo cáo thiếu hàng thất bại',
        totalVariants: 'Biến thể thiếu hàng',
        totalShortage: 'Tổng thiếu hụt',
        ordersAffected: 'Đơn bị ảnh hưởng',
        searchVariant: 'Mã biến thể',
        variantIdPlaceholder: 'Mã biến thể...',
        style: 'Style',
        stylePlaceholder: 'Style...',
        fromDate: 'Từ ngày',
        toDate: 'Đến ngày',
        sortBy: 'Sắp xếp theo',
        loading: 'Đang tải biến thể thiếu hàng...',
        noShortage: 'Không tìm thấy biến thể thiếu hàng',
        noShortageDesc: 'Tất cả biến thể hiện có đủ tồn kho.',
        variantId: 'Mã biến thể',
        color: 'Màu',
        size: 'Size',
        stock: 'Tồn kho',
        demand: 'Nhu cầu',
        shortage: 'Thiếu hụt',
        orders: 'Đơn hàng',
        day: 'ngày',
        days: 'ngày',
        sortOptions: {
          shortage: 'Mức thiếu hụt',
          orders_count: 'Số đơn hàng',
          demand: 'Nhu cầu',
          variant_id: 'Mã biến thể',
        },
        ordersTable: {
          title: 'Đơn hàng bị ảnh hưởng',
          noOrders: 'Không có đơn hàng bị ảnh hưởng',
          orderId: 'Mã đơn',
          refId: 'Ref ID',
          seller: 'Người bán',
          quantity: 'SL',
          shortage: 'Thiếu hụt',
          daysPending: 'Số ngày',
          action: 'Thao tác',
          view: 'Xem',
        },
      },
      auditLogs: {
        title: 'Lịch sử kiểm kê kho',
        subtitle: 'Theo dõi toàn bộ thay đổi tồn kho và lịch sử thao tác',
        loading: 'Đang tải lịch sử kiểm kê...',
        noLogs: 'Không tìm thấy lịch sử kiểm kê',
        failedToLoadLogs: 'Tải lịch sử kiểm kê thất bại',
        failedToLoadOptions: 'Tải tùy chọn bộ lọc thất bại',
        failedToCheckProductions: 'Kiểm tra sản xuất của biến thể thất bại',
        searchVariant: 'Mã biến thể',
        enterVariantId: 'Nhập mã biến thể...',
        style: 'Style',
        allStyles: 'Tất cả style',
        color: 'Màu',
        allColors: 'Tất cả màu',
        size: 'Size',
        allSizes: 'Tất cả size',
        action: 'Hành động',
        allActions: 'Tất cả hành động',
        orderId: 'Mã đơn',
        enterOrderId: 'Nhập mã đơn...',
        dateFrom: 'Từ ngày',
        dateTo: 'Đến ngày',
        dateTime: 'Ngày / Giờ',
        user: 'Người dùng',
        product: 'Sản phẩm',
        before: 'Trước',
        after: 'Sau',
        change: 'Thay đổi',
        reason: 'Lý do',
        stockIncrease: 'Tăng tồn kho',
        stockDecrease: 'Giảm tồn kho',
        stockAdjustment: 'Điều chỉnh tồn kho',
        stockMapped: 'Đã map tồn kho',
        stockRestored: 'Khôi phục tồn kho',
        manualAdjustment: 'Điều chỉnh thủ công',
        system: 'Hệ thống',
        na: 'N/A',
        clickToCheckProductions: 'Bấm để xem productions',
        variantProductions: {
          title: 'Production của biến thể',
          variantId: 'Mã biến thể',
          productionId: 'Production #',
          orderId: 'Mã đơn',
          orderRef: 'Order Ref',
          quantity: 'Số lượng',
          units: 'đơn vị',
          noProductions: 'Không tìm thấy production cho biến thể này',
          close: 'Đóng',
          status: {
            pending: 'Đang chờ',
            pickup: 'Đã lấy',
            mapped: 'Đã khớp',
            completed: 'Hoàn thành',
            cancelled: 'Đã hủy',
            unknown: 'Không xác định',
          },
        },
      },
    },
    trackOrder: {
      title: 'Theo dõi đơn hàng',
      scanner: {
        httpsRequired: 'Camera yêu cầu kết nối HTTPS. Vui lòng sử dụng https://',
        notSupported: 'Camera không được hỗ trợ trên thiết bị/trình duyệt này',
        permissionDenied:
          'Quyền truy cập camera bị từ chối. Vui lòng cho phép truy cập camera trong cài đặt trình duyệt.',
        notFound: 'Không tìm thấy camera trên thiết bị này',
        inUse: 'Camera đang được sử dụng bởi ứng dụng khác',
        error: 'Lỗi camera: {error}',
        startFailed: 'Không thể khởi động camera: {error}',
        invalidQr: 'Định dạng mã QR không hợp lệ',
        tapToScan: 'Chạm để quét mã QR...',
      },
      status: {
        readySuccess: '✓ Đã đánh dấu thiết kế là sẵn sàng!',
        updateFailed: 'Cập nhật trạng thái thất bại',
        rejectSuccess:
          '❌ Đã từ chối thiết kế - Đơn hàng được hoàn trả về bộ phận hỗ trợ',
        rejectFailed: 'Từ chối thiết kế thất bại',
        rejectConfirm:
          '⚠️ Bạn có chắc chắn muốn TỪ CHỐI thiết kế này không?\n\nHành động này sẽ:\n- Đặt lại toàn bộ tiến trình quy trình\n- Khôi phục tồn kho\n- Hoàn trả đơn hàng về Hỗ trợ\n\nHành động này không thể hoàn tác.',
        uncheckError:
          'Không thể bỏ chọn thiết kế đã được đánh dấu là sẵn sàng',
        newOrder: 'Đơn mới',
        inProduction: 'Đang sản xuất',
        shipped: 'Đã gửi',
        delivered: 'Đã giao',
        cancelled: 'Đã hủy',
        closed: 'Đã đóng',
        returnToSupport: 'Trả về hỗ trợ',
        cancelledOrder: '❌ Đơn hàng đã hủy',
        closedOrder: '❌ Đơn hàng đã đóng',
        markStaffReady: 'Đánh dấu nhân viên đã xong',
        complete: '✓ Hoàn thành',
        waitingQC: '⏳ Đang chờ KCS',
        waitingPacking: '⏳ Đang chờ đóng gói',
        waitingShipout: '⏳ Đang chờ vận chuyển',
      },
      labels: {
        order: 'Đơn hàng',
        user: 'Người dùng',
        orderStatus: 'Trạng thái đơn hàng',
        orderItems: 'Sản phẩm',
        item: 'Mục',
        variantId: 'Mã biến thể',
        styleSizeStock: 'KIỂU - SIZE - TỒN KHO',
        color: 'MÀU SẮC',
        designPositions: 'Vị trí thiết kế',
        updating: 'Đang cập nhật...',
        staff: 'Nhân viên',
        qc: 'KCS',
        pack: 'Đóng gói',
        ship: 'Vận chuyển',
      },
      pes: {
        stitches: 'MŨI KHÂU',
        width: 'RỘNG',
        height: 'CAO',
        colors: 'MÀU',
      },
      needle: {
        title: 'Gán kim (1-12) - {action}',
        dragOrTap: 'Kéo hoặc Chạm để đổi',
        tapToSwap: 'Chạm kim khác để đổi',
        hint: '✓ Đã chọn kim {needle}. Chạm kim khác để đổi.',
      },
      colorSequence: {
        title: 'Thứ tự màu',
        sequence: '#',
        needle: 'KIM#',
        color: 'MÀU',
        code: 'MÃ',
        name: 'TÊN',
        chart: 'BẢNG MÀU',
      },
      loading: 'Đang tải thông tin đơn hàng...',
      notFound: 'Không tìm thấy đơn hàng',
      failedLoad: 'Không thể tải thông tin đơn hàng',
      tryAgain: 'Thử lại',
      confirmModal: {
        markAsReady: 'Đánh dấu {position} là sẵn sàng?',
        cancel: 'Hủy',
        confirm: 'Xác nhận',
      },
    },
  },
  en: {
    language: {
      label: 'Change language',
      vietnamese: 'Vietnamese',
      english: 'English',
    },
    sidebar: {
      workspace: {
        teamName: 'Admin Workspace',
        teamPlan: 'Next.js + shadcn/ui',
        general: 'General',
        overview: 'Overview',
        tasks: 'Tasks',
        apps: 'Apps',
        users: 'Users',
        support: 'Support',
        helpCenter: 'Help Center',
        notifications: 'Notifications',
        settings: 'Settings',
        profile: 'Profile',
      },
      lemiex: {
        teamName: 'Lemiex Workspace',
        teamPlan: 'Role-aware sidebar',
        overview: 'Overview',
        commerce: 'Commerce',
        operations: 'Operations',
        supportTools: 'Support Tools',
        administration: 'Administration',
        dashboard: 'Dashboard',
        welcome: 'Welcome',
        orders: 'Orders',
        designs: 'Designs',
        products: 'Products',
        catalog: 'Catalog',
        productVariants: 'Product Variants',
        stores: 'Stores',
        tickets: 'Tickets',
        stockManagement: 'Stock Management',
        stockDashboard: 'Dashboard',
        manageStock: 'Manage Stock',
        productions: 'Productions',
        shortageReport: 'Shortage Report',
        shortageByVariant: 'Shortage by Variant',
        auditLogs: 'Audit Logs',
        hrPayroll: 'HR & Payroll',
        attendances: 'Attendances',
        payrollReport: 'Payroll Report',
        salaryTiers: 'Salary Tiers',
        embroideryProgress: 'Embroidery Progress',
        trackings: 'Trackings',
        videos: 'Videos',
        wallets: 'Wallets',
        transactions: 'Transactions',
        pendingFund: 'Pending Fund',
        refunds: 'Refunds',
        surcharge: 'Surcharge',
        debits: 'Debits',
        quickAccess: {
          balance: 'Balance',
          add: 'Add',
          orderIdPlaceholder: 'Order ID',
          openTrackPage: 'Open track page',
          scanQr: 'Scan QR',
          scanUnavailable: 'QR scanning will be added later.',
          scanInvalid: 'Invalid QR code.',
          scanHttpsRequired: 'HTTPS is required to access the camera on this device.',
          scanNotSupported: 'Camera is not supported in this browser.',
          scanCameraDenied: 'Camera access denied or not available.',
          orderIdRequired: 'Please enter an order ID',
          addFundTitle: 'Create a fund request',
          addFundDescription: 'Send a wallet transaction request for admin approval.',
          transactionId: 'Transaction ID',
          generateTransactionId: 'Generate a new transaction ID',
          processing: 'Processing...',
          submit: 'Submit request',
          addFundPending: 'Your fund request has been submitted and is pending approval.',
          addFundFailed: 'Failed to create the transaction request.',
        },
        staffReport: 'Staff Report',
        systems: 'Systems',
        users: 'Users',
        permissions: 'Permissions',
        tiers: 'Tiers',
      },
    },
    command: {
      placeholder: 'Search screens or actions...',
      empty: 'No results found.',
      theme: 'Theme',
      light: 'Light',
      dark: 'Dark',
      system: 'System',
    },
    profile: {
      manageProfile: 'Profile',
      billing: 'Billing',
      notifications: 'Notifications',
      signOut: 'Sign out',
      roleLabel: 'Role',
      signOutTitle: 'Sign out',
      signOutDesc:
        'Are you sure you want to sign out? You will need to sign in again to access your account.',
      cancel: 'Cancel',
    },
    pagination: {
      rowsPerPage: 'Rows per page',
      pageOf: 'Page {current} of {total}',
      goToFirstPage: 'Go to first page',
      goToPreviousPage: 'Go to previous page',
      goToPage: 'Go to page {page}',
      goToNextPage: 'Go to next page',
      goToLastPage: 'Go to last page',
    },
    orders: {
      title: 'Orders',
      count: 'orders',
      refresh: 'Refresh',
      embroidery: 'Embroidery',
      print: 'Print',
      loadErrorTitle: 'Unable to load orders',
      empty: 'No orders found for the current filters.',
      noOrderIds: 'No order IDs match the current filters.',
      copiedOrderIds: 'Copied {count} order ID(s).',
      noTrackingNumbers: 'No tracking numbers found for selected orders',
      copiedTrackingNumbers: 'Copied {count} tracking number(s)',
      copyTrackingFailed: 'Failed to copy tracking numbers',
      selectAtLeastOneOrder: 'Please select at least one order',
      buyLabelFailed: 'Failed to create shipping',
      labelCreated: 'Shipping created successfully! Tracking: {tracking}',
      labelJobsDispatched:
        '{count} shipping orders dispatched successfully!',
      createOrder: 'Create Order',
      confirmBuyLabel: 'Confirm Create Shipping',
      confirmBuyLabelDesc:
        'Are you sure you want to create shipping for {count} order(s)?',
      confirmPurchase: 'Confirm',
      processing: 'Processing...',
      copyTracking: 'Copy Tracking',
      buyLabel: 'Create shipping',
      headers: {
        order: 'Order',
        seller: 'Seller',
        ticket: 'Ticket',
        priority: 'Priority',
        embType: 'Emb Type',
        fulfillStatus: 'Fulfill Status',
        items: 'Items',
        tracking: 'Tracking',
        printCost: 'Print Cost',
        shipping: 'Shipping',
        totalCost: 'Total Cost',
        payment: 'Payment',
        created: 'Created',
        actions: 'Actions',
      },
      status: {
        unknown: 'Unknown',
        noRefId: 'No ref ID',
        noVariant: 'No variant',
        hasTicket: 'Has ticket',
        normal: 'Normal',
        priority: 'Priority',
        noItems: 'No items',
        itemCount: '{count} item(s)',
        noTracking: '-',
        label: 'Label',
        convert: 'Convert',
        na: 'N/A',
        unnamedItem: 'Unnamed item',
        front: 'Front',
      },
      actions: {
        view: 'View',
        timeline: 'Timeline',
        edit: 'Edit',
        support: 'Support',
        goToStores: 'Go to Stores',
        ticketExistsTitle: 'Ticket Already Exists',
        ticketExistsDesc:
          'This order already has one or more support tickets. Would you like to view existing tickets or create a new one?',
        viewExistingTickets: 'View Existing Tickets',
        createNewTicket: 'Create New Ticket',
        pending: '{label} action will be wired next.',
        remakeDesign: 'Remake Des',
        remakeQr: 'Remake QR',
      },
      timelineModal: {
        title: 'Order Timeline',
        orderPrefix: 'Order',
        loading: 'Loading timeline...',
        empty: 'No timeline events found',
        loadError: 'Failed to load timeline',
        close: 'Close',
        columns: {
          action: 'Action',
          description: 'Description',
          createdAt: 'Created At',
          updatedAt: 'Updated At',
        },
      },
      detail: {
        backToOrders: 'Back to Orders',
        loadingOrder: 'Loading order details...',
        orderNotFound: 'Order not found',
        orderInfo: 'Order Information',
        sellerInfo: 'Seller Information',
        shippingInfo: 'Shipping Information',
        orderItems: 'Items',
        pricing: 'Pricing',
        actionsTitle: 'Actions',
        orderStt: 'Order',
        referenceId: 'Reference ID',
        sellerRef: 'Seller Ref',
        paymentStatus: 'Payment Status',
        createdAt: 'Created At',
        username: 'Username',
        email: 'Email',
        tier: 'Tier',
        store: 'Store',
        service: 'Service',
        method: 'Method',
        trackingId: 'Tracking ID',
        address: 'Address',
        shippingLabel: 'Shipping Label',
        viewLabel: 'View Label',
        convertLabel: 'Convert Label',
        viewConvert: 'View Convert',
        qrCodes: 'QR Codes',
        download: 'Download',
        downloadAll: 'Download All',
        downloadingAll: 'Downloading...',
        downloadAllSuccess: 'Downloaded {success}/{total} QR codes',
        mergedImages: 'Merged Images',
        quantity: 'Quantity',
        printCost: 'Print Cost',
        shippingCost: 'Shipping Cost',
        extraFee: 'Extra Fee',
        refundFee: 'Refund Fee',
        totalCost: 'Total Cost',
        profitMargin: 'Profit Margin',
        updatingLabel: 'Updating label...',
        updateLabel: 'Update Label',
        updateLabelSuccess: 'Label updated successfully',
        updateLabelFailed: 'Failed to update label',
        cancelOrder: 'Cancel Order',
        sellerCancelConfirm:
          'Are you sure you want to cancel order #{id}? This action cannot be undone.',
        sellerCancelSuccess: 'Order cancelled successfully',
        sellerCancelFailed: 'Failed to cancel order',
        videos: 'Videos',
        noData: 'No data',
      },
      createOrderDialog: {
        storeRequiredTitle: 'Store required',
        storeRequiredDesc:
          'You need at least one store before creating an order.',
        categoryTitle: 'Create New Order',
        categoryDesc: 'Select product category to continue.',
        embroideryTitle: 'Embroidery',
        embroideryDesc:
          'T-Shirts, Hoodies, Sweatshirts with embroidered designs.',
        tumblerTitle: 'Tumbler Print',
        tumblerDesc: 'Tumblers and mugs with printed designs.',
        typeTitle: 'Select Order Type',
        typeDescEmbroidery: 'Embroidery',
        typeDescTumbler: 'Print Order',
        noDesignTitle: 'No Design',
        noDesignDesc: 'Blank products without any design file.',
        labelShipTitle: 'Label Ship',
        labelShipDesc:
          'Orders with design files and TikTok shipping labels.',
        sellerShipTitle: 'Seller Ship',
        sellerShipDesc:
          'Orders with design files and shipping address.',
        tumblerLabelShipTitle: 'Label Ship',
        tumblerLabelShipDesc:
          'Print orders with a pre-existing shipping label.',
        tumblerSellerShipTitle: 'Seller Ship',
        tumblerSellerShipDesc:
          'Print orders with a delivery address.',
      },
      createForm: {
        labelShipTitle: 'Create Order - Label Ship',
        labelShipSubtitle:
          'Create embroidery orders with TikTok shipping label URLs and complete design assets.',
        sellerShipTitle: 'Create Order - Seller Ship',
        sellerShipSubtitle:
          'Create embroidery orders with seller shipping address and full design package.',
        backToOrders: 'Back to Orders',
        orderInformation: 'Order Information',
        shippingInformation: 'Shipping Information',
        shippingAddress: 'Shipping Address',
        productsAndDesignFiles: 'Products & Design Files',
        productsAndDesignFilesDesc:
          'Add print products, mockups and design files for this order.',
        orderReferenceId: 'Order Reference ID',
        storeApiKey: 'Store / API Key',
        sellerReference: 'Seller Reference',
        orderStatus: 'Order Status',
        shippingMethod: 'Shipping Method',
        shippingService: 'Shipping Service',
        fulfillmentPriority: 'Fulfillment Priority',
        shippingLabelUrl: 'TikTok Shipping Label URL',
        shippingLabelHint:
          'This flow has lower shipping cost. Recipient address is not required.',
        orderNotes: 'Order Notes',
        recipientName: 'Recipient Name',
        phoneNumber: 'Phone Number',
        streetAddress: 'Street Address',
        apartmentSuite: 'Apartment, suite, etc.',
        city: 'City',
        stateProvince: 'State / Province',
        zipCode: 'ZIP / Postal Code',
        country: 'Country',
        productCardTitle: 'Product #{index}',
        productCardDesc:
          'Variant, mockups and design files for this item.',
        productVariant: 'Product Variant',
        variantId: 'Variant ID',
        quantity: 'Quantity',
        productName: 'Product Name',
        mockupFrontUrl: 'Mockup Front URL',
        mockupBackUrl: 'Mockup Back URL',
        mockupPreview: 'Mockup Preview',
        addFrontMockupUrl: 'Add a front mockup URL',
        designFiles: 'Design Files',
        designFilesDesc: 'Upload design files for each side of the product.',
        addDesignSide: 'Add Design Side',
        designTitle: 'Design #{index}',
        position: 'Position',
        designFileUrl: 'Design File URL',
        addProduct: 'Add Product',
        remove: 'Remove',
        cancel: 'Cancel',
        createOrder: 'Create Order',
        creating: 'Creating...',
        loadingStores: 'Loading stores...',
        selectedStore: 'Selected store: {name}',
        storesAvailable: '{count} store(s) available',
        noStoresFound: 'No stores found. Enter API key manually.',
        standardShippingMethod: 'standard',
        fixedUsps: 'USPS',
        optionLabels: {
          orderStatus: {
            new_order: 'New Order',
            on_hold: 'On Hold',
            confirm: 'Confirm',
            test_order: 'Test Order',
          },
          shippingService: {
            USPS: 'USPS',
            UPS: 'UPS',
            FedEx: 'FedEx',
          },
          country: {
            US: 'United States',
            CA: 'Canada',
            GB: 'United Kingdom',
            AU: 'Australia',
            DE: 'Germany',
            FR: 'France',
            JP: 'Japan',
            VN: 'Vietnam',
          },
          designPosition: {
            front: 'Front',
            back: 'Back',
            neck: 'Neck',
          },
        },
        productPicker: {
          product: 'Product',
          size: 'Size',
          loadingProducts: 'Loading products...',
          selectProduct: 'Select product',
          loadingSizes: 'Loading sizes...',
          selectSize: 'Select size',
          resolvingVariant: 'Resolving variant...',
          variantId: 'Variant ID',
          chooseAll: 'Choose product and size to resolve a variant',
        },
        upload: {
          upload: 'Upload',
          uploading: 'Uploading...',
          uploadFailed: 'Upload failed',
          uploadImageOrPaste: 'Upload image or paste URL',
          previewAlt: 'File preview',
        },
        placeholders: {
          orderRefId: 'e.g. ORDER-12345',
          manualApiKey: 'Enter API key manually',
          sellerRef: 'e.g. SHOP-12345',
          selectStore: 'Select a store',
          selectStatus: 'Select status',
          selectShippingMethod: 'Select shipping method',
          selectShippingService: 'Select shipping service',
          selectPriority: 'Select priority',
          shippingLabel:
            'https://open-fs.tiktokshops.us/label/12345.pdf',
          notes: 'Add special instructions or handling notes',
          recipientName: 'John Doe',
          phone: '+1234567890',
          street1: '123 Main Street',
          street2: 'Apartment, suite, unit, building, floor',
          city: 'New York',
          state: 'NY',
          zip: '10001',
          selectCountry: 'Select country',
          variantId: 'Select product and size',
          productName: 'Product name shown in the order',
          mockupFront: 'https://example.com/mockup-front.png',
          mockupBack: 'https://example.com/mockup-back.png',
          selectPosition: 'Select position',
          designFileUrl: 'https://example.com/design.png',
        },
        validation: {
          orderRefRequired: 'Order reference ID is required.',
          apiKeyRequired: 'Store / API key is required.',
          shippingLabelRequired: 'Shipping label URL is required.',
          shippingAddressRequired: 'Please complete the shipping address.',
          variantRequired: 'Each product must have a variant ID.',
          productNameRequired: 'Each product must have a product name.',
          mockupRequired: 'Each product must have a front mockup URL.',
          designFileRequired:
            'Each product must include at least one design file.',
        },
        submit: {
          successWithId: 'Order created successfully. Order ID: {id}',
          success: 'Order created successfully.',
          failed: 'Failed to create order',
        },
      },
      editForm: {
        title: 'Edit Order',
        reference: 'Reference',
        loading: 'Loading order details...',
        loadingFailed: 'Failed to load order details',
        cannotEdit: 'Cannot Edit',
        sellerBlockReason:
          'Seller can only edit orders with status: new_order or on_hold. Current: {status}',
        generalInformation: 'General Information',
        shippingDetails: 'Shipping Details',
        addressInformation: 'Address Information',
        orderItems: 'Order Items',
        note: 'Note',
        shippingMethod: 'Shipping Method',
        shippingService: 'Shipping Service',
        shippingLabelUrl: 'Shipping Label URL',
        addressLine1: 'Address Line 1',
        addressLine2: 'Address Line 2',
        fullName: 'Full Name',
        city: 'City',
        state: 'State / Province',
        zipCode: 'Zip / Postal Code',
        country: 'Country',
        phone: 'Phone',
        mockupImages: 'Mockup Images',
        frontViewUrl: 'Front View URL',
        backViewUrl: 'Back View URL',
        printFilesDesigns: 'Print Files / Designs',
        addPosition: 'Add Position',
        noPrintFiles: 'No print files added.',
        positionPlaceholder: 'Position...',
        url: 'URL',
        imageUrl: 'Image URL',
        pdfUrl: 'PDF URL',
        embUrl: 'EMB URL',
        pesUrl: 'PES URL',
        cancel: 'Cancel',
        saveChanges: 'Save Changes',
        saving: 'Saving...',
        saveSuccess: 'Order updated successfully',
        noChanges: 'No changes detected.',
        saveFailed: 'Failed to update order',
        viewFile: 'View file',
        changeVariant: 'Change variant',
        currentVariant: 'Current variant',
        newVariant: 'New variant',
        variantChangeLocked:
          'Variant can only be changed while the order is unpaid and in new_order or on_hold.',
        revertVariant: 'Revert',
        variantChangedHint: 'Color/size and price will update after saving.',
      },
      filters: {
        orderId: 'ORDER ID',
        variantId: 'VARIANT ID',
        refId: 'REF ID',
        trackingNumber: 'TRACKING NUMBER',
        search: 'Search',
        clearAll: 'Clear All',
        getIds: 'Get IDs',
        filters: 'Filters',
        excludeStatus: 'EXCLUDE STATUS',
        shippingInfo: 'SHIPPING INFO',
        missingShippingInfo: 'Missing Info (Label/Tracking/Convert)',
        fulfillStatus: 'FULFILL STATUS',
        paymentStatus: 'PAYMENT STATUS',
        productAttributes: 'PRODUCT ATTRIBUTES',
        style: 'STYLE',
        color: 'COLOR',
        size: 'SIZE',
        seller: 'SELLER',
        embType: 'EMB TYPE',
        productName: 'PRODUCT NAME',
        dateFrom: 'DATE FROM',
        dateTo: 'DATE TO',
        shippedDateRange: 'SHIP-OUT DATE (CARRIER RECONCILIATION)',
        shippedDateFrom: 'SHIPPED FROM',
        shippedDateTo: 'SHIPPED TO',
        shippedToday: 'Today',
        shippedDateHint:
          'Noon cutoff: a selected day = the batch that left at noon that day (orders scanned from 12:00 the previous day up to 12:00 on the selected day).',
        sortBy: 'SORT BY',
        sortOrder: 'SORT ORDER',
        placeholders: {
          orderId: 'e.g. 59 58 80',
          variantId: 'Variant ID',
          refId: 'Reference ID',
          trackingNumber: 'Tracking #',
          selectStyle: 'Select Style',
          selectColor: 'Select Color',
          selectSize: 'Select Size',
          allSellers: 'All Sellers',
          allTypes: 'All Types',
          productName: 'Product Name',
          createdDate: 'Created Date',
          ascending: 'Ascending',
        },
        selectStyle: 'Select Style',
        selectColor: 'Select Color',
        selectSize: 'Select Size',
        allSellers: 'All Sellers',
        allTypes: 'All Types',
      },
      paymentStatuses: {
        pending: 'Pending',
        paid: 'Paid',
        partial_refund: 'Partial Refund',
        refunded: 'Refunded',
        failed: 'Failed',
      },
      fulfillStatuses: {
        new_order: 'New Order',
        confirm: 'Confirm',
        pending_stock: 'Pending Stock',
        in_stock: 'In Stock',
        producing: 'Producing',
        qc_pass: 'QC Pass',
        packed: 'Packed',
        shipped: 'Shipped',
        on_hold: 'On Hold',
        return_to_support: 'Return To Support',
        cancelled: 'Cancelled',
        cancelled_refund_shipping: 'Cancelled (Refund Shipping)',
        closed: 'Closed',
        test_order: 'Test Order',
      },
      sortBy: {
        created_at: 'Created At',
        updated_at: 'Updated At',
        shipped_at: 'Shipped At',
        id: 'Order ID',
        ref_id: 'Reference ID',
      },
      sortOrder: {
        asc: 'Ascending',
        desc: 'Descending',
      },
    },
    productVariants: {
      title: 'Product Variants',
      count: 'products',
      loading: 'Loading products...',
      loadError: 'Unable to load products',
      empty: 'No products match the current filters.',
      tabs: {
        embroidery: 'Embroidery',
        print: 'Print',
      },
      columns: {
        product: 'Product',
        templateUrl: 'Template',
        colors: 'Colors',
        sizes: 'Sizes',
        variants: 'Variants',
        totalStock: 'Total Stock',
        priceRange: 'Price Range',
        status: 'Status',
        actions: 'Actions',
      },
      filters: {
        search: 'Search',
        searchPlaceholder: 'Search by name, brand, style...',
        style: 'Style',
        stylePlaceholder: 'Filter by style...',
        brand: 'Brand',
        brandPlaceholder: 'Filter by brand...',
        status: 'Status',
        allStatus: 'All Status',
        sortBy: 'Sort By',
        newestFirst: 'Newest First',
        oldestFirst: 'Oldest First',
        nameAz: 'Name (A-Z)',
        nameZa: 'Name (Z-A)',
        brandAz: 'Brand (A-Z)',
        brandZa: 'Brand (Z-A)',
        clearFilters: 'Clear Filters',
      },
      status: {
        noBrand: 'No brand',
        noStyle: 'No style',
        noTemplate: 'No template',
        noColors: 'No colors',
        noSizes: 'No sizes',
        active: 'active',
        activeLabel: 'Active',
        inactiveLabel: 'Inactive',
        na: 'N/A',
        to: 'to',
      },
      actions: {
        importCsv: 'Import CSV',
        createProduct: 'Create Product',
        importPending: 'CSV import flow will be connected next.',
        stock: 'Stock',
        view: 'View',
        delete: 'Delete',
        confirmDelete: 'Are you sure you want to delete product "{name}"?',
        deleteSuccess: 'Product deleted successfully',
        deleteFailed: 'Failed to delete product',
        deletePending: 'Delete flow for "{name}" will be connected next.',
      },
      importDialog: {
        title: 'Import products from CSV',
        description: 'Upload a CSV file, preview the data, then import it.',
        downloadTemplate: 'Download template',
        downloadCurrentData: 'Download current data',
        clickToSelect: 'Click to select a CSV file',
        orDragDrop: 'or drag and drop it here',
        selectCsvFile: 'Please select a CSV file',
        preview: 'Preview',
        previewFailed: 'Failed to preview CSV',
        import: 'Import',
        importSuccess: 'Products imported successfully',
        importFailed: 'Failed to import products',
        products: 'Products',
        newProducts: 'New products',
        existingProducts: 'Existing products',
        newTag: 'NEW',
        updateTag: 'UPDATE',
        imported: 'Imported',
        failed: 'Failed',
        errors: 'Errors',
        done: 'Done',
      },
      stockDialog: {
        title: 'Update Stock',
        description: 'Adjust inventory for a product variant.',
        addStock: 'Add Stock',
        subtractStock: 'Subtract Stock',
        color: 'Color',
        size: 'Size',
        quantity: 'Quantity',
        quantityPlaceholder: 'Enter quantity',
        selectColor: 'Select color',
        selectSize: 'Select size',
        validation: 'Please provide valid stock information.',
        updating: 'Updating...',
        updateFailed: 'Failed to update stock',
        addSuccess: 'Stock added successfully',
        subtractSuccess: 'Stock subtracted successfully',
      },
      detail: {
        loading: 'Loading product details...',
        loadError: 'Failed to load product details',
        notFound: 'Product not found',
        back: 'Back to Product Variants',
        active: 'Active',
        inactive: 'Inactive',
        brand: 'Brand',
        style: 'Style',
        warehouse: 'Warehouse',
        category: 'Category',
        print: 'Print',
        embroidery: 'Embroidery',
        created: 'Created',
        updated: 'Updated',
        editProduct: 'Edit Product',
        totalVariants: 'Total Variants',
        totalStock: 'Total Stock',
        priceRange: 'Price Range',
        colors: 'Colors',
        sizes: 'Sizes',
        variantsTitle: 'Variants',
        variantsCount: 'variants',
        noData: 'N/A',
        save: 'Save',
        cancel: 'Cancel',
        edit: 'Edit',
        delete: 'Delete',
        confirmDeleteVariant: 'Are you sure you want to delete variant {id}?',
        deleteVariantSuccess: 'Variant deleted successfully',
        deleteVariantFailed: 'Failed to delete variant',
        deletePending: 'Delete flow for variant {id} will be connected next.',
        variantUpdated: 'Variant updated successfully',
        updateFailed: 'Failed to update variant',
        pricingSaved: 'Tier pricing updated successfully',
        viewPricing: 'View Pricing',
        setPricing: 'Set Pricing',
        pricing: {
          title: 'Tier Pricing',
          noVariant: 'No variant selected',
          readOnly: 'Read only',
          production: 'Production Costs',
          shipping: 'Shipping Costs',
          type: 'Type',
          close: 'Close',
          cancel: 'Cancel',
          saving: 'Saving...',
          save: 'Save Changes',
          failed: 'Failed to update tier pricing',
        },
        columns: {
          variantId: 'Variant ID',
          color: 'Color',
          size: 'Size',
          stock: 'Stock',
          supplierPrice: 'Supplier Price',
          tierPricing: 'Tier Pricing',
          weight: 'Weight',
          dimensions: 'Dimensions',
          status: 'Status',
          actions: 'Actions',
        },
      },
      createForm: {
        title: 'Create Product',
        description: 'Create a new product with variants and pricing.',
        productInfo: 'Product Information',
        variants: 'Variants',
        pricing: 'Pricing',
        productName: 'Product Name',
        style: 'Style',
        brand: 'Supplier',
        warehouse: 'Warehouse',
        productNamePlaceholder: 'e.g., Printed T-Shirt',
        stylePlaceholder: 'e.g., Box-S',
        brandPlaceholder: 'e.g., Print Workshop VN',
        warehousePlaceholder: 'e.g., Main Warehouse',
        mockupUrl: 'Mockup URL',
        category: 'Category',
        status: 'Status',
        active: 'Active',
        inactive: 'Inactive',
        addVariant: 'Add Variant',
        noVariantsYet: 'No variants added yet. Click add variant to begin.',
        variant: 'Variant',
        removeVariant: 'Remove variant',
        variantId: 'Variant ID',
        variantIdPlaceholder: 'e.g., G5000-BLK-S',
        sku: 'SKU',
        skuPlaceholder: 'e.g., SKU-G5000-BLK-S',
        color: 'Color',
        colorPlaceholder: 'e.g., Black',
        size: 'Size',
        sizePlaceholder: 'e.g., S',
        stock: 'Stock',
        supplierPrice: 'Supplier Price',
        weight: 'Weight (g)',
        dimensions: 'Dimensions (L x W x H)',
        addPrice: 'Add Price',
        noPricesAdded: 'No prices added',
        tier: 'Tier',
        priceType: 'Price Type',
        price: 'Price',
        cancel: 'Cancel',
        create: 'Create Product',
        creating: 'Creating...',
        productNameRequired: 'Product name is required',
        variantIdRequired: 'Variant ID is required',
        createSuccess: 'Product created successfully',
        createFailed: 'Failed to create product',
      },
    },
    storesPage: {
      title: 'Stores Management',
      subtitle: 'Manage all stores',
      totalStores: 'total stores',
      addStore: 'Add New Store',
      searchPlaceholder: 'Search by store name, username, or email...',
      allStatus: 'All Status',
      loading: 'Loading stores...',
      noStores: 'No stores found',
      noStoresAvailable: 'No stores available',
      failedToLoad: 'Failed to load stores',
      columns: {
        id: 'ID',
        user: 'User',
        storeName: 'Store Name',
        status: 'Status',
        createdAt: 'Created At',
        actions: 'Actions',
      },
      status: {
        active: 'Active',
        unconfirmed: 'Unconfirmed',
        banned: 'Banned',
      },
      dialog: {
        createTitle: 'Add New Store',
        createSubtitle: 'Create a new store for a seller',
        editTitle: 'Edit Store',
        editSubtitle: 'Update store information',
        loadingUsers: 'Loading users...',
        loadingStore: 'Loading store data...',
        user: 'User (Seller)',
        selectUser: 'Select a user',
        storeName: 'Store Name',
        enterStoreName: 'Enter store name',
        apiKey: 'API Key',
        status: 'Status',
        cancel: 'Cancel',
        create: 'Create Store',
        creating: 'Creating...',
        update: 'Update Store',
        updating: 'Updating...',
        onlySelf: 'You can only create stores for yourself',
        onlyAdmin: 'Only Admin can change store owner',
        statusHint: 'This will update the user status',
        apiKeyHint: 'Auto-generated API key. Click refresh to generate a new one.',
        apiKeyEditHint: 'Click refresh to generate a new API key',
        refreshKey: 'Generate new API key',
        successCreate: 'Store created successfully!',
        successUpdate: 'Store updated successfully!',
        failedCreate: 'Failed to create store. Please try again.',
        failedUpdate: 'Failed to update store. Please try again.',
        failedLoadUsers: 'Failed to load users. Please try again.',
        failedLoadStore: 'Failed to load store data. Please try again.',
        validation: {
          requiredUser: 'Please select a user',
          requiredName: 'Store name is required',
          requiredApiKey: 'API Key is required',
        },
        active: 'Active',
        unconfirmed: 'Unconfirmed',
        banned: 'Banned',
      },
    },
    usersPage: {
      title: 'User Management',
      addFund: 'Add Fund',
      addNew: 'Add New User',
      backToList: 'Back to Users',
      backToDetail: 'Back to Details',
      createTitle: 'Add New User',
      editTitle: 'Edit User',
      viewTitle: 'User Details',
      accountInfo: 'Account Information',
      userDetails: 'User Details',
      integrationSettings: 'Integration Settings',
      debitSettings: 'Debit Settings',
      additionalOptions: 'Additional Options',
      username: 'Username',
      email: 'Email',
      role: 'Role',
      statusLabel: 'Status',
      registrationDate: 'Registration Date',
      firstName: 'First Name',
      lastName: 'Last Name',
      phone: 'Phone',
      dob: 'Date of Birth',
      address: 'Address',
      webhookUrl: 'Webhook URL',
      telegramId: 'Telegram ID',
      apiKey: 'API Key',
      maxDebit: 'Max Debit',
      maxDateDebit: 'Max Date Debit',
      minDateDebit: 'Min Date Debit',
      balanceLabel: 'Balance',
      supportUs: 'Support Us',
      privateSeller: 'Private Seller',
      days: 'days',
      yes: 'Yes',
      no: 'No',
      filters: {
        search: 'Search by name, email, username...',
        allStatus: 'All Status',
        allRoles: 'All Roles',
        allTiers: 'All Tiers',
      },
      status: {
        active: 'Active',
        unconfirmed: 'Unconfirmed',
        banned: 'Banned',
      },
      columns: {
        username: 'Username',
        fullName: 'Full Name',
        role: 'Role',
        email: 'Email',
        balance: 'Balance',
        tier: 'Tier',
        registrationDate: 'Registration Date',
        status: 'Status',
        actions: 'Actions',
      },
      form: {
        accountInfo: 'Account Information',
        userDetails: 'User Details',
        integrationSettings: 'Integration Settings',
        debitSettings: 'Debit Settings',
        additionalOptions: 'Additional Options',
        email: 'Email',
        username: 'Username',
        password: 'Password',
        confirmPassword: 'Confirm Password',
        newPassword: 'New Password',
        confirmNewPassword: 'Confirm New Password',
        leaveBlank: 'Leave blank to keep current password',
        role: 'Role',
        status: 'Status',
        firstName: 'First Name',
        lastName: 'Last Name',
        phone: 'Phone',
        dob: 'Date of Birth',
        address: 'Address',
        webhookUrl: 'Webhook URL',
        telegramId: 'Telegram ID',
        apiKey: 'API Key',
        maxDebit: 'Max Debit',
        maxDateDebit: 'Max Date Debit',
        minDateDebit: 'Min Date Debit',
        supportUs: 'Support Us',
        yes: 'Yes',
        no: 'No',
        optional: '(optional)',
        loadingRoles: 'Loading roles...',
        noRoles: 'No roles available',
        submit: 'Create User',
        update: 'Update User',
        cancel: 'Cancel',
      },
      addFundModal: {
        title: 'Add Fund to Seller',
        selectSeller: 'Select Seller',
        loadingSellers: 'Loading sellers...',
        selectPlaceholder: '-- Select a seller --',
        currentBalance: 'Current Balance',
        type: 'Type',
        deposit: 'Deposit (+)',
        withdraw: 'Withdraw (-)',
        amount: 'Amount',
        enterAmount: 'Enter amount',
        note: 'Note',
        notePlaceholder: 'e.g. Monthly deposit',
        newBalance: 'New Balance',
        cancel: 'Cancel',
        submit: 'Confirm',
        selectSellerRequired: 'Please select a seller',
        invalidAmount: 'Please enter a valid amount',
        fundFailed: 'Failed to add fund',
        fundSuccess:
          'Successfully {action} ${amount} {direction} {user}. New balance: ${balance}',
      },
      tiers: {
        silver: 'Silver',
        gold: 'Gold',
        platinum: 'Platinum',
        diamond: 'Diamond',
      },
      roles: {
        admin: 'Admin',
        seller: 'Seller',
        user: 'User',
        supplier: 'Supplier',
        staff: 'Staff',
        support: 'Support',
        designer: 'Designer',
        finance: 'Finance',
      },
      notFound: 'No users found',
      loadFailed: 'Failed to load user information',
      deleteConfirm: 'Are you sure you want to delete this user?',
      deleteSuccess: 'User deleted successfully',
      deleteFailed: 'Failed to delete user',
      createSuccess: 'User created successfully!',
      updateSuccess: 'User updated successfully!',
      loading: 'Loading...',
      deleteTitle: 'Delete',
      error: 'An error occurred',
      na: 'N/A',
    },
    permissionsPage: {
      title: 'Permissions',
      subtitle: 'Manage role-based access control matrix',
      syncPermissions: 'Sync Permissions',
      syncing: 'Syncing...',
      permission: 'Permission',
      save: 'Save',
      saving: 'Saving...',
      adminHasAllPermissions: 'Admin has all permissions',
      savePermissions: 'Save permissions',
      selectAllInGroup: 'Select all in group',
      noPermissions: 'No permissions found',
      loadFailed: 'Failed to load permissions data',
      saveSuccess: 'Permissions saved successfully',
      saveFailed: 'Failed to save permissions',
      syncSuccess: 'Permissions synced successfully',
      syncFailed: 'Failed to sync permissions',
      otherGroup: 'Other',
      createRole: 'Create Role',
      newRoleTitle: 'Create New Role',
      newRoleDescription: 'Add a new role. You can assign permissions after creation.',
      roleName: 'Role Name (system)',
      roleNamePlaceholder: 'e.g. Manager (letters, numbers, underscore)',
      roleDisplayName: 'Display Name',
      roleDisplayNamePlaceholder: 'e.g. Senior Manager',
      roleDescription: 'Description',
      roleDescriptionPlaceholder: 'Optional description',
      cancel: 'Cancel',
      create: 'Create',
      creating: 'Creating...',
      createSuccess: 'Role created successfully',
      createFailed: 'Failed to create role',
      deleteRole: 'Delete Role',
      confirmDelete: 'Are you sure you want to delete this role?',
      builtInRole: 'Built-in role (cannot delete)',
      deleteSuccess: 'Role deleted successfully',
      deleteFailed: 'Failed to delete role',
    },
    tiersPage: {
      title: 'Tiers',
      createTier: 'Create Tier',
      loading: 'Loading tiers...',
      noTiers: 'No tiers available',
      tierBadge: 'Tier',
      extraFees: 'Extra Fees',
      refundFees: 'Refund Fees',
      embroideryFees: 'Embroidery Fees',
      priorityFees: 'Priority Fees',
      addExtraFee: 'Add Extra Fee',
      addRefundFee: 'Add Refund Fee',
      addEmbroideryFee: 'Add Embroidery Fee',
      addPriorityFee: 'Add Priority Fee',
      emptyExtraFees: 'No extra fees configured',
      emptyRefundFees: 'No refund fees configured',
      emptyEmbroideryFees: 'No embroidery fees configured',
      emptyPriorityFees: 'No priority fees configured',
      minStitch: 'Min Stitch',
      maxStitch: 'Max Stitch',
      amount: 'Amount ($)',
      stitch: 'Stitch',
      type: 'Type',
      name: 'Name',
      displayName: 'Display Name',
      description: 'Description',
      price: 'Price ($)',
      actions: 'Actions',
      edit: 'Edit',
      delete: 'Delete',
      createTitle: 'Create Tier',
      editTitle: 'Edit Tier',
      tierName: 'Tier Name',
      tierNamePlaceholder: 'Enter tier name',
      save: 'Save',
      cancel: 'Cancel',
      creating: 'Creating...',
      saving: 'Saving...',
      deleting: 'Deleting...',
      confirmDeleteTitle: 'Confirm Delete',
      confirmDeleteDescription: 'This action cannot be undone.',
      extraFeeDialogTitle: 'Extra Fee',
      refundFeeDialogTitle: 'Refund Fee',
      embroideryFeeDialogTitle: 'Embroidery Fee',
      priorityFeeDialogTitle: 'Priority Fee',
      embroideryType: 'Embroidery Type',
      embroideryTypePlaceholder: 'Select embroidery type',
      priorityName: 'Priority Name',
      priorityDisplayNamePlaceholder: 'Priority',
      priorityDescriptionPlaceholder: 'Standard processing 3-5 days',
      standard: 'Standard',
      metallic: 'Metallic',
      glow: 'Glow',
      puff: 'Puff',
      normalPriority: 'Normal',
      rushPriority: 'Priority',
      requiredTierName: 'Tier name is required',
      requiredFields: 'Please fill in all required fields',
      tierCreated: 'Tier created successfully',
      tierUpdated: 'Tier updated successfully',
      tierDeleted: 'Tier deleted successfully',
      feeCreated: 'Fee created successfully',
      feeUpdated: 'Fee updated successfully',
      feeDeleted: 'Fee deleted successfully',
      failedLoad: 'Failed to load tiers',
      failedCreateTier: 'Failed to create tier',
      failedUpdateTier: 'Failed to update tier',
      failedDeleteTier: 'Failed to delete tier',
      failedSaveFee: 'Failed to save fee',
      failedDeleteFee: 'Failed to delete fee',
    },
    dashboardPage: {
      title: 'Dashboard',
      subtitle: 'Overview of orders, revenue, stock, and recent system activity.',
      loading: 'Loading dashboard...',
      failedLoad: 'Failed to load dashboard statistics',
      timeRangeLabel: 'Time range',
      today: 'Today',
      yesterday: 'Yesterday',
      last7Days: '7D',
      last30Days: '30D',
      last90Days: '90D',
      lastYear: '1Y',
      sellerScope: 'Seller view',
      sellerScopeDescription: 'Statistics are scoped to your own store activity.',
      totalOrders: 'Orders',
      totalRevenue: 'Revenue',
      productsVariants: 'Products',
      totalStock: 'Stock',
      ordersThisPeriod: '{count} orders this period',
      revenueThisPeriod: '{amount} this period',
      variants: '{count} variants · {active} active',
      lowStockWarning: '{count} variants are low on stock',
      totalDeposits: 'Deposits',
      totalWithdrawals: 'Withdrawals',
      totalPayments: 'Payments',
      pendingTransactions: 'Pending',
      transactionsThisPeriod: '{count} transactions this period',
      productSalesQuantity: 'Product sales quantity',
      top5Products: 'Top product performance over time',
      revenueByPaymentStatus: 'Revenue by payment status',
      dailyBreakdown: 'Daily revenue breakdown',
      dailyOrders: 'Daily orders',
      ordersPerDay: 'Orders created per day',
      transactionsOverview: 'Transactions overview',
      dailyTransactions: 'Daily transaction amounts by type',
      noSalesData: 'No product sales data',
      noRevenueData: 'No revenue data',
      noOrderData: 'No daily order data',
      noTransactionData: 'No transaction data',
      ordersByPaymentStatus: 'Orders by payment status',
      ordersByFulfillStatus: 'Orders by fulfill status',
      topProducts: 'Top products',
      recentOrders: 'Recent orders',
      noRecentOrders: 'No recent orders',
      noTopProducts: 'No top products',
      orderId: 'Order ID',
      store: 'Store',
      items: 'Items',
      paymentStatus: 'Payment',
      fulfillStatus: 'Fulfill',
      created: 'Created',
      viewAll: 'View all',
      vsPrevious: 'vs previous period',
      empty: 'No data available',
      units: 'units',
      // Orders compact card rows
      ordersTotalRow: 'Total',
      ordersShippingRow: 'Shipping',
      ordersDeliveredRow: 'Delivered',
      ordersOnHoldRow: 'On Hold',
      // Revenue card rows
      revenueTotalRow: 'Total Revenue',
      revenuePeriodRow: 'This Period',
      revenuePaidRow: 'Paid',
      revenuePendingRow: 'Pending Approval',
      // Products & stock card
      productsStockTitle: 'Products & Stock',
      productsRow: 'Products',
      variantsRow: 'Variants',
      stockRow: 'Stock',
      lowStockRow: 'Low Stock',
      // Financials card
      financialsTitle: 'Financials',
      depositsRow: 'Deposits',
      withdrawalsRow: 'Withdrawals',
      paymentsRow: 'Payments',
      txPeriodRow: 'Transactions This Period',
      // Status breakdown
      paymentBreakdownTitle: 'Orders by Payment',
      ordersUnit: 'orders',
      // Ranking tables
      rankingProductsTitle: 'Product Ranking',
      rankingSellersTitle: 'Seller Ranking',
      rankingUpdated: 'Updated:',
      rankCol: 'Rank',
      productNameCol: 'Product Name',
      soldQtyCol: 'Units Sold',
      sellerNameCol: 'Seller',
      totalItemsCol: 'Total Items',
      noSellerData: 'No seller data',
      // Funnel
      funnelCellSize: '1 cell = {size} orders',
      // Production flow labels
      flowNewOrder: 'New Order',
      flowConfirmed: 'Confirmed',
      flowProducing: 'Producing',
      flowShipped: 'Shipped',
      // Shop stats table
      shopStatsTitle: 'Order Stats by Shop',
      shopColIndex: '#',
      shopColName: 'Shop',
      shopColTotal: 'Total Orders',
      shopColRefund: 'Refunded',
      shopColPaid: 'Paid',
      shopColProcessing: 'Processing',
      shopColOnHold: 'On Hold',
      shopColSellers: 'Sellers',
      noShopData: 'No data',
    },
    staffReportPage: {
      title: 'Staff Performance Report',
      subtitle: 'Track staff workflow performance and efficiency',
      filters: {
        dateFrom: 'Date From',
        dateTo: 'Date To',
        staffMember: 'Staff Member',
        allStaff: 'All Staff',
        apply: 'Apply Filters',
        refresh: 'Refresh Data',
      },
      summary: {
        title: 'Staff Performance Summary',
        staffName: 'Staff Name',
        username: 'Username',
        itemsProcessed: 'Items Processed',
        contribution: 'Percentage Contribution',
        share: 'Share',
        noData: 'No performance data found for selected period.',
        total: 'Total',
        items: 'items',
      },
      details: {
        title: 'Processing Activity Details',
        staffName: 'Staff Name',
        username: 'Username',
        orderItem: 'Order / Item',
        order: 'Order',
        item: 'Item',
        metaKey: 'Meta Key',
        processedAt: 'Processed At',
        noData: 'No activity details found.',
      },
      loading: 'Loading report data...',
      failedLoadList: 'Failed to load staff list',
      failedLoadReport: 'Failed to load report data',
    },
    attendancesPage: {
      title: 'Attendance Management',
      subtitle: 'Track employee work hours and logs',
      importBtn: 'Import .txt File',
      importing: 'Importing...',
      filters: {
        employeeName: 'Employee Name',
        searchPlaceholder: 'Search by name...',
        customRange: 'Custom Range',
        from: 'From',
        to: 'To',
        date: 'Single Date',
        month: 'Month',
        clear: 'Clear Filters',
      },
      columns: {
        id: 'ID',
        employeeName: 'Employee Name',
        totalDays: 'Total Days',
        week: 'Week',
        month: 'Month',
        year: 'Year',
      },
      days: 'days',
      logs: {
        show: 'Show',
        entries: 'entries',
        showing: 'Showing',
        of: 'of',
        records: 'records',
        noRecords: 'No records',
        date: 'Date',
        checkIn: 'Check In',
        checkOut: 'Check Out',
        totalWork: 'Total Work',
        loading: 'Loading...',
        noRecordsFound: 'No records found',
        completeMissing: 'Update',
        previous: 'Previous',
        next: 'Next',
        pageOf: 'Page {current} of {total}',
      },
      editModal: {
        title: 'Complete Missing Attendance',
        employee: 'Employee',
        workDate: 'Work Date',
        existingTime: 'Existing Time',
        missingType: 'Missing Type',
        checkIn: 'Check In',
        checkOut: 'Check Out',
        time: 'Time',
        cancel: 'Cancel',
        save: 'Save',
        saving: 'Saving...',
        validation: {
          timeRequired: 'Please select a time',
        },
      },
      messages: {
        failedLoadData: 'Failed to load attendance data',
        failedLoadLogs: 'Failed to load user logs',
        importSuccess: 'Imported successfully',
        importFailed: 'Import failed',
        noRecords: 'No attendance records found.',
        updateSuccess: 'Attendance updated successfully',
        updateFailed: 'Failed to update attendance',
      },
    },
    payrollPage: {
      title: 'Payroll Report',
      subtitle: 'Track payroll for {period} with {count} employees',
      setRate: 'Set Rate',
      rewardsPenalties: 'Rewards / Penalties',
      month: 'Month',
      customRange: 'Custom Range',
      from: 'From',
      to: 'To',
      totalHours: 'Total Hours',
      totalSalary: 'Total Salary',
      netTotal: 'Net Salary',
      companyTaxTotal: 'Co. Tax',
      missingRate: 'Missing Rate',
      staffs: 'staffs',
      noEmployees: 'No employees found',
      employee: 'Employee',
      rateHr: 'Rate/Hr',
      hours: 'Hours',
      adjustments: 'Adjustments',
      grossSalary: 'Gross',
      netSalary: 'Net',
      companyTax: 'Co. Tax',
      totalSalaryCol: 'Total',
      actions: 'Actions',
      edit: 'Edit',
      log: 'Log',
      view: 'View',
      clickToEdit: 'Click to edit',
      save: 'Save',
      cancel: 'Cancel',
      close: 'Close',
      loading: 'Loading payroll...',
      selectEmployee: 'Please select at least one employee',
      selectTierOrRate: 'Please select a tier or enter a custom rate',
      fillTypeAmount: 'Please fill in type and amount',
      rateSetSuccess: '{success}/{total} salary rates set successfully',
      failedSetRate: 'Failed to set salary rate',
      rateUpdated: 'Salary rate updated successfully',
      failedUpdateRate: 'Failed to update salary rate',
      adjustmentSuccess: '{success}/{total} adjustments created successfully',
      failedAdjustment: 'Failed to create adjustments',
      failedLoadPayroll: 'Failed to load payroll data',
      fieldUpdated: 'Updated successfully',
      failedUpdate: 'Failed to update',
      setRateModal: {
        title: 'Set Salary Rate',
        selectEmployees: 'Select Employees',
        selectAll: 'Select All',
        selected: 'selected',
        selectTier: 'Select Tier',
        or: 'OR',
        customRate: 'Custom Hourly Rate',
        effectiveFrom: 'Effective From',
        setting: 'Setting...',
        setRateBtn: 'Set Rate',
      },
      editRateModal: {
        title: 'Edit Salary Rate',
        hourlyRate: 'Hourly Rate',
        detachNote: 'Entering a custom rate will detach this employee from the current tier.',
        note: 'Note',
        reasonPlaceholder: 'Reason for salary update',
        saving: 'Saving...',
      },
      salaryLog: {
        title: 'Salary Log',
        noHistory: 'No salary history available',
        custom: 'Custom',
        from: 'From',
        ended: 'Ended',
        current: 'Current',
      },
      adjustmentModal: {
        title: 'Add Reward / Penalty',
        type: 'Type',
        typePlaceholder: 'Ex: Bonus, Late fine...',
        amount: 'Amount',
        action: 'Action',
        addReward: 'Add Reward',
        deductPenalty: 'Deduct Penalty',
        date: 'Date',
        processing: 'Processing...',
        add: 'Add',
        deduct: 'Deduct',
      },
      adjustmentDetail: {
        title: 'Adjustment Details',
        noAdjustments: 'No adjustments available',
        typeReason: 'Type / Reason',
      },
      guide: {
        title: 'Payroll Guide',
        close: 'Close',
        steps: [
          {
            icon: '📊',
            title: 'Review working hours',
            desc: 'Check payroll by month or custom range before making salary decisions.',
          },
          {
            icon: '💰',
            title: 'Assign salary rates',
            desc: 'Set hourly rates by tier or by custom amount for selected employees.',
          },
          {
            icon: '⚖️',
            title: 'Apply rewards and penalties',
            desc: 'Use adjustments to add bonuses or deduct penalties from payroll.',
          },
          {
            icon: '📈',
            title: 'Finalize net salary',
            desc: 'Inline edit net salary and company tax to reflect the final payroll total.',
          },
        ],
      },
    },
    payrollTiersPage: {
      title: 'Salary Tiers',
      subtitle: 'Manage payroll salary tiers',
      createTier: 'Create Tier',
      tierName: 'Tier Name',
      hourlyRate: 'Hourly Rate',
      currency: 'Currency',
      description: 'Description',
      actions: 'Actions',
      noTiers: 'No salary tiers available',
      createTitle: 'Create Salary Tier',
      editTitle: 'Edit Salary Tier',
      deleteTitle: 'Delete Salary Tier',
      namePlaceholder: 'Enter tier name',
      ratePlaceholder: '15.00',
      descriptionPlaceholder: 'Optional notes for this tier',
      create: 'Create',
      creating: 'Creating...',
      save: 'Save',
      saving: 'Saving...',
      cancel: 'Cancel',
      delete: 'Delete',
      deleting: 'Deleting...',
      confirmDelete: 'Are you sure you want to delete this tier?',
      fillTypeAmount: 'Please fill in tier name and hourly rate',
      tierCreated: 'Salary tier created successfully',
      tierUpdated: 'Salary tier updated successfully',
      tierDeleted: 'Salary tier deleted successfully',
      failedLoadTiers: 'Failed to load salary tiers',
      failedCreateTier: 'Failed to create salary tier',
      failedUpdateTier: 'Failed to update salary tier',
      failedDeleteTier: 'Failed to delete salary tier',
    },
    ticketsPage: {
      title: 'Support Tickets',
      subtitle: 'Manage support tickets',
      totalTickets: 'total tickets',
      tabs: {
        all: 'All Tickets',
        new: 'New',
        solved: 'Solved',
      },
      filters: {
        ticketId: 'Ticket ID',
        orderId: 'Order ID',
        subject: 'Subject',
        allSellers: 'All Sellers',
        allSupport: 'All Support',
      },
      columns: {
        id: 'ID',
        orderId: 'Order ID',
        subject: 'Subject',
        status: 'Status',
        userReply: 'User Reply',
        lastReply: 'Last Reply',
        owner: 'Owner',
        updated: 'Updated',
        actions: 'Actions',
      },
      status: {
        new: 'New',
        solved: 'Solved',
      },
      actions: {
        view: 'View',
        solve: 'Solve',
      },
      noTicketsTitle: 'No tickets found',
      noTicketsDescriptionFiltered: 'Try adjusting your filters',
      noTicketsDescriptionEmpty: 'No tickets available',
      loadFailed: 'Failed to load tickets',
      statusUpdated: 'Ticket status updated successfully!',
      statusUpdateFailed: 'Failed to update ticket status',
      createSuccess: 'Support ticket created successfully!',
      createDialog: {
        createTitle: 'Create Support Ticket',
        subject: 'Subject',
        subjectPlaceholder: 'Brief description of the issue',
        message: 'Message',
        messagePlaceholder: 'Describe the issue in detail...',
        attachFile: 'Attach File (Optional)',
        clickToUpload: 'Click to upload',
        fileHint: 'JPG, PNG, GIF, PDF (max 10MB)',
        cancel: 'Cancel',
        creating: 'Creating...',
        createNew: 'Create Ticket',
        subjectRequired: 'Subject is required',
        messageRequired: 'Message is required',
        orderIdMissing: 'Order ID is missing. Please try again.',
        fileSizeError: 'File size must be less than 10MB',
        fileTypeError: 'Only JPG, PNG, GIF, and PDF files are allowed',
        createFailed: 'Failed to create ticket. Please try again.',
      },
    },
    ticketDetailPage: {
      back: 'Back',
      backToTickets: 'Back to Tickets',
      loading: 'Loading ticket...',
      notFound: 'Ticket not found',
      loadDetailFailed: 'Failed to load ticket details',
      fileSizeError: 'File size must be less than 10MB',
      fileTypeError: 'Only JPG, PNG, GIF, and PDF files are allowed',
      viewPdf: 'View PDF',
      noMessages: 'No messages yet. Start the conversation!',
      placeholder: 'Type your message... (Shift+Enter for new line)',
      placeholderImage: 'Image selected - ready to send',
      enterMessage: 'Please enter a message or attach a file',
      sendFailed: 'Failed to send message',
      statusUpdated: 'Status updated successfully!',
      statusUpdateFailed: 'Failed to update status',
      markSolved: 'Mark as Solved',
      reopen: 'Reopen',
      remove: 'Remove',
      status: {
        new: 'New',
        solved: 'Solved',
      },
      unknown: 'Unknown',
    },
    walletTransactionsPage: {
      title: 'Wallet Transactions',
      subtitle: 'Transaction history',
      totalTransactions: 'total transactions',
      exportAll: 'Export All',
      exportPayments: 'Export Payments',
      exportDeposits: 'Export Deposits',
      exportRefunds: 'Export Refunds',
      tabs: {
        all: 'All Transactions',
        payments: 'Payments (Debit)',
        deposits: 'Deposits (Credit)',
        refunds: 'Refunds',
      },
      filters: {
        allSellers: 'All Sellers',
        fromDate: 'From Date',
        toDate: 'To Date',
        search: 'Search...',
      },
      columns: {
        id: 'ID',
        transactionId: 'Transaction ID',
        seller: 'Seller',
        orderId: 'Order ID',
        store: 'Store',
        type: 'Type',
        amount: 'Amount',
        balance: 'Balance',
        note: 'Note',
        status: 'Status',
        date: 'Date',
      },
      status: {
        completed: 'Completed',
        pending: 'Pending',
        failed: 'Failed',
      },
      type: {
        add_fund: 'Add Fund',
        order_payment: 'Order Payment',
        refund: 'Refund',
      },
      summary: {
        total: 'Total',
        page: 'This page',
      },
      loading: 'Loading transactions...',
      noTransactionsTitle: 'No transactions found',
      noTransactionsDescriptionFiltered: 'Try adjusting your filters',
      noTransactionsDescriptionEmpty: 'No transactions available',
      loadFailed: 'Failed to load transactions',
      loadSellersFailed: 'Failed to load sellers',
      exporting: 'Exporting transactions...',
      exportSuccess: 'Transactions exported successfully!',
      exportFailed: 'Failed to export transactions',
      na: 'N/A',
      none: 'No messages',
    },
    pendingFundPage: {
      title: 'Pending Fund Requests',
      subtitle: 'Review and approve fund deposit requests from sellers',
      showing: 'Showing {count} pending request(s)',
      loading: 'Loading...',
      noRequests: 'No pending requests',
      allCaught: 'All fund requests have been processed.',
      fetchError: 'Failed to load pending requests',
      confirmApprove: 'Are you sure you want to approve this fund request?',
      approveSuccess: 'Fund request approved successfully!',
      approveFailed: 'Failed to approve request',
      rejectSuccess: 'Fund request rejected',
      rejectFailed: 'Failed to reject request',
      approve: 'Approve',
      reject: 'Reject',
      columns: {
        id: 'ID',
        seller: 'Seller',
        type: 'Type',
        amount: 'Amount',
        transactionId: 'Transaction ID',
        note: 'Note',
        date: 'Date',
        actions: 'Actions',
      },
      rejectModal: {
        title: 'Reject Fund Request',
        subtitle: 'Please provide a reason for rejection (optional)',
        placeholder: 'Enter rejection reason...',
        cancel: 'Cancel',
        confirm: 'Confirm Reject',
      },
      type: {
        deposit: 'Deposit',
        refund: 'Refund',
      },
      na: 'N/A',
    },
    partnerAppsPage: {
      title: 'Partner Apps',
      subtitle: 'Copy auth links and manage partner app connection settings.',
      addApp: 'Add Partner App',
      loading: 'Loading partner apps...',
      empty: 'No partner apps found',
      copied: 'Auth link copied',
      noAuthLink: 'This partner app does not have an auth link yet',
      na: 'N/A',
      columns: {
        name: 'Name',
        linkAuth: 'Link Auth',
        proxyStatus: 'Proxy Status',
        status: 'Status',
        actions: 'Actions',
      },
      copyLink: 'Copy Link Auth',
      edit: 'Edit',
      dialog: {
        createTitle: 'Create Partner App',
        editTitle: 'Edit Partner App',
        name: 'Name',
        slug: 'Slug',
        authUrl: 'Auth URL',
        proxyStatus: 'Proxy Status',
        status: 'Status',
        cancel: 'Cancel',
        create: 'Create',
        update: 'Update',
        successCreate: 'Partner app created successfully',
        successUpdate: 'Partner app updated successfully',
      },
    },
    partnerStoresPage: {
      title: 'Partner Stores',
      subtitle: 'Create and manage connected partner shops separately from legacy stores.',
      addStore: 'Add Partner Store',
      searchPlaceholder: 'Search by name, code, user, account...',
      loading: 'Loading partner stores...',
      empty: 'No partner stores found',
      failed: 'Failed to load partner stores',
      syncTitle: 'Sync Orders',
      syncDescription: 'Confirm to sync the latest orders from this partner shop.',
      syncConfirm: 'Start Sync',
      syncCancel: 'Cancel',
      syncProgressTitle: 'Syncing orders...',
      syncProgressDescription: 'Please wait while the system processes partner orders.',
      syncDone: 'Orders synced successfully',
      na: 'N/A',
      columns: {
        id: 'ID',
        partner: 'Partner',
        name: 'Name',
        user: 'User',
        status: 'Status',
        totalOrders: 'Total Orders',
        accountNo: 'Account No',
        actions: 'Actions',
      },
      dialog: {
        createTitle: 'Add Partner Store',
        editTitle: 'Edit Partner Store',
        storeName: 'Store Name',
        storeCode: 'Shop Code',
        user: 'Staff',
        partnerApp: 'Partner App',
        status: 'Status',
        accountNo: 'Account No',
        cancel: 'Cancel',
        create: 'Submit',
        update: 'Update',
        successCreate: 'Partner store created successfully',
        successUpdate: 'Partner store updated successfully',
        na: 'N/A',
      },
    },
    partnerSyncOrdersPage: {
      title: 'Synced Orders',
      subtitle: 'Review the latest synced partner orders before moving into the main flow.',
      loading: 'Loading synced orders...',
      empty: 'No synced orders yet. Run sync from Partner Stores first.',
      filters: {
        store: 'Store',
        orderNo: 'Partner Order',
        status: 'Status',
        fulfillment: 'Fulfillment',
        allStores: 'All Stores',
        allStatuses: 'All Statuses',
        allFulfillment: 'All Fulfillment',
        orderNoPlaceholder: 'Search partner order...',
        search: 'Search',
        clearAll: 'Clear All',
        pending: 'Pending',
        paid: 'Paid',
        cancelled: 'Cancelled',
        noFulfillment: 'No fulfillment',
        ready: 'Ready',
        shipped: 'Shipped',
      },
      columns: {
        id: 'ID',
        store: 'Store',
        customer: 'Customer',
        user: 'User',
        partnerOrder: 'TikTok Order',
        tracking: 'Tracking',
        items: 'Items',
        discount: 'Discount',
        total: 'Total',
        status: 'Status',
        fulfillment: 'Fulfillment',
        note: 'Note',
        actions: 'Actions',
      },
      labels: {
        sku: 'SKU',
        qty: 'QTY',
        buyLabel: 'Create shipping',
        buyLabels: 'Create shipping',
        edit: 'Edit',
        ship: 'Ship',
        delete: 'Delete',
      },
    },
    stock: {
      manage: {
        title: 'Stock Management',
        description: 'Keep the legacy stock flow on the new system layout.',
        importExport: 'Import/Export',
        loading: 'Loading stock data...',
        loadError: 'Failed to load stock data',
        summary: {
          totalStock: 'Total Stock',
          reserved: 'Reserved',
          available: 'Available',
          lowStockItems: 'Low Stock Items',
        },
        filters: {
          variantId: 'Variant ID',
          sku: 'SKU',
          style: 'Style',
          color: 'Color',
          size: 'Size',
          stockLevel: 'Stock Level',
          status: 'Status',
          searchPlaceholder: 'Search...',
          allStyles: 'All Styles',
          allColors: 'All Colors',
          allSizes: 'All Sizes',
          all: 'All',
          lowStock: 'Low Stock (< 5)',
          outOfStock: 'Out of Stock',
          active: 'Active',
          inactive: 'Inactive',
          reset: 'Reset',
        },
        empty: {
          title: 'No products found',
          description: 'Try adjusting your filters',
        },
        tabs: {
          variants: 'variants',
        },
        bulk: {
          selected: '{count} variants selected',
          hint: 'Choose an operation to apply to all selected variants',
          clearSelection: 'Clear selection',
          operation: 'Operation',
          selectOperation: 'Select operation...',
          stockOperations: 'Stock Operations',
          statusOperations: 'Status Operations',
          addStock: 'Add to Current Stock',
          subtractStock: 'Subtract from Current Stock',
          setStock: 'Set Stock Level',
          activate: 'Activate',
          deactivate: 'Deactivate',
          amountToAdd: 'Amount to Add',
          amountToSubtract: 'Amount to Subtract',
          newStockLevel: 'New Stock Level',
          enterValue: 'Enter value...',
          reason: 'Reason (Optional)',
          reasonPlaceholder: 'e.g., New shipment arrived...',
          applyTo: 'Apply to {count} variant(s)',
          selectVariantsAndAction:
            'Please select variants and an operation',
          enterValidStock:
            'Please enter a valid stock value (0 or greater)',
          success: '{count} variants updated successfully',
        },
        table: {
          variantId: 'Variant ID',
          sku: 'SKU',
          style: 'Style',
          color: 'Color',
          size: 'Size',
          stock: 'Stock',
          reserved: 'Reserved',
          available: 'Available',
          active: 'Active',
          actions: 'Actions',
          save: 'Save',
          cancel: 'Cancel',
          edit: 'Edit',
          history: 'History',
          noVariants: 'No variants found for this product',
          stockCannotBeNegative: 'Stock cannot be negative',
          noChangesToSave: 'No changes to save',
          variantUpdated: 'Variant updated successfully',
          updateFailed: 'Failed to update variant',
          variantStatusUpdated: 'Variant status updated',
        },
        historyDialog: {
          title: 'Stock History',
          currentStock: 'Current Stock',
          loading: 'Loading history...',
          noRecords: 'No history records found',
          increase: 'Increase',
          decrease: 'Decrease',
          adjust: 'Adjust',
          import: 'Import',
          skuUpdated: 'SKU Updated',
          styleUpdated: 'Style Updated',
          activated: 'Activated',
          deactivated: 'Deactivated',
          bulkUpdate: 'Bulk Update',
          bulkOperation: 'Bulk Operation',
          operation: 'Operation',
          showingLast: 'Showing last 20 changes',
          sku: 'SKU',
          style: 'STYLE',
          active: 'ACTIVE',
          empty: '(empty)',
          variantId: 'Variant ID',
        },
        importExportDialog: {
          title: 'Stock Import/Export',
          import: 'Import',
          export: 'Export',
          importInstructions: 'Import Instructions:',
          instructionFile: 'File must be CSV format',
          instructionId:
            'Required: At least one identifier (Variant ID or SKU)',
          instructionFields:
            'Optional fields: Stock, Style, Color, Size, Product',
          instructionUpdate: 'Only fields present in CSV will be updated',
          stockOperationType: 'Stock Operation Type',
          setStock: 'Set Stock (Replace)',
          addStock: 'Add Stock (Increase)',
          subtractStock: 'Subtract Stock (Decrease)',
          hintSet: 'Replace current stock with values from file',
          hintAdd: 'Add values from file to current stock',
          hintSubtract: 'Subtract values from file from current stock',
          selectCsvFile: 'Select CSV File',
          chooseFile: 'Choose file...',
          downloadTemplate: 'Download Template',
          skuImport: 'SKU Import',
          variantImport: 'Variant Import',
          fullImport: 'Full Import',
          skuTemplateHint: 'Download SKU template (SKU, Stock)',
          variantTemplateHint: 'Download Variant template (Variant ID, Stock)',
          fullTemplateHint: 'Download Full template (All fields)',
          importing: 'Importing...',
          importBtn: 'Import',
          importResults: 'Import Results',
          success: 'Success:',
          failed: 'Failed:',
          errors: 'Errors:',
          moreErrors: '... and {count} more errors',
          exportStockData: 'Export Stock Data:',
          exportDesc: 'Export all stock data to CSV file including:',
          exportFields1: 'Variant ID, SKU, Product Name',
          exportFields2: 'Style, Color, Size',
          exportFields3: 'Stock, Reserved, Available',
          exportFields4: 'Status (Active/Inactive)',
          exportPreview1:
            'The export will include all variants with current stock information.',
          exportPreview2:
            'Export time depends on the number of variants in your inventory.',
          exporting: 'Exporting...',
          exportToCsv: 'Export to CSV',
          pleaseSelectCsv: 'Please select a CSV file',
          pleaseSelectFile: 'Please select a file',
          importSuccess: 'Import completed successfully',
          importFailed: 'Import failed',
          failedToImport: 'Failed to import stock',
          exportSuccess: 'Export completed successfully',
          exportFailed: 'Export failed',
          failedToExport: 'Failed to export stock',
        },
      },
      shortage: {
        title: 'Shortage Report',
        subtitleWithCount: '{count} pending orders',
        subtitleAllGood: 'No pending orders',
        viewByVariant: 'View by Variant',
        exportCsv: 'Export CSV',
        exporting: 'Exporting shortage report...',
        exportSuccess: 'Report exported successfully',
        exportFailed: 'Failed to export report',
        failedToLoadReport: 'Failed to load shortage report',
        loading: 'Loading pending orders...',
        noPendingOrders: 'No pending orders found',
        totalPendingOrders: 'Total Pending Orders',
        ordersWithShortage: 'Orders With Shortage',
        variantsAffected: 'Variants Affected',
        totalShortage: 'Total Shortage',
        searchOrder: 'Search Order',
        orderIdRefIdPlaceholder: 'Order ID / Ref ID...',
        searchVariant: 'Search Variant',
        variantIdPlaceholder: 'Variant ID...',
        pendingReason: 'Pending Reason',
        fromDate: 'From Date',
        toDate: 'To Date',
        sortBy: 'Sort By',
        orderId: 'Order ID',
        refId: 'Ref ID',
        seller: 'Seller',
        items: 'Items',
        shortage: 'Shortage',
        daysPending: 'Days Pending',
        action: 'Action',
        view: 'View',
        day: 'day',
        days: 'days',
        awaitingProcessing: 'Awaiting Processing',
        awaitingProcessingDesc:
          'Stock is available for this order. The system is processing it and will allocate stock shortly.',
        missingFiles: 'Missing Files',
        noItems: 'No Items',
        noItemsDesc: 'This order has no items. Please check the order details.',
        unknownReason: 'Unknown Reason',
        unknownReasonDesc:
          'This order is pending for an unknown reason. It may be on manual hold or waiting for other business logic.',
        status: {
          shortage: 'Stock Shortage',
          missing_files: 'Missing Files',
          awaiting_allocation: 'Awaiting Allocation',
          no_items: 'No Items',
          unknown: 'Unknown',
        },
        sortOptions: {
          seller_username: 'Seller',
          days_pending: 'Days Pending',
          shortage: 'Shortage Amount',
          created_at: 'Created Date',
        },
        variantTable: {
          title: 'Shortage Variants',
          noVariants: 'No shortage variants',
          variantId: 'Variant ID',
          style: 'Style',
          color: 'Color',
          size: 'Size',
          stock: 'Stock',
          demand: 'Demand',
          shortage: 'Shortage',
        },
      },
      shortageByVariant: {
        title: 'Shortage by Variant',
        subtitleWithCount: '{count} variants with shortage',
        subtitleAllGood: 'No shortage variants',
        viewByOrder: 'View by Order',
        failedToLoad: 'Failed to load shortage report',
        totalVariants: 'Variants with Shortage',
        totalShortage: 'Total Shortage',
        ordersAffected: 'Orders Affected',
        searchVariant: 'Variant ID',
        variantIdPlaceholder: 'Variant ID...',
        style: 'Style',
        stylePlaceholder: 'Style...',
        fromDate: 'From Date',
        toDate: 'To Date',
        sortBy: 'Sort By',
        loading: 'Loading shortage variants...',
        noShortage: 'No shortage variants found',
        noShortageDesc: 'All variants have sufficient stock.',
        variantId: 'Variant ID',
        color: 'Color',
        size: 'Size',
        stock: 'Stock',
        demand: 'Demand',
        shortage: 'Shortage',
        orders: 'Orders',
        day: 'day',
        days: 'days',
        sortOptions: {
          shortage: 'Shortage Amount',
          orders_count: 'Orders Count',
          demand: 'Demand',
          variant_id: 'Variant ID',
        },
        ordersTable: {
          title: 'Affected Orders',
          noOrders: 'No affected orders',
          orderId: 'Order ID',
          refId: 'Ref ID',
          seller: 'Seller',
          quantity: 'Qty',
          shortage: 'Shortage',
          daysPending: 'Days',
          action: 'Action',
          view: 'View',
        },
      },
      auditLogs: {
        title: 'Stock Audit Logs',
        subtitle: 'Track all stock changes and history',
        loading: 'Loading audit logs...',
        noLogs: 'No audit logs found',
        failedToLoadLogs: 'Failed to load audit logs',
        failedToLoadOptions: 'Failed to load filter options',
        failedToCheckProductions: 'Failed to check variant productions',
        searchVariant: 'Variant ID',
        enterVariantId: 'Enter variant ID...',
        style: 'Style',
        allStyles: 'All Styles',
        color: 'Color',
        allColors: 'All Colors',
        size: 'Size',
        allSizes: 'All Sizes',
        action: 'Action',
        allActions: 'All Actions',
        orderId: 'Order ID',
        enterOrderId: 'Enter order ID...',
        dateFrom: 'Date From',
        dateTo: 'Date To',
        dateTime: 'Date/Time',
        user: 'User',
        product: 'Product',
        before: 'Before',
        after: 'After',
        change: 'Change',
        reason: 'Reason',
        stockIncrease: 'Stock Increase',
        stockDecrease: 'Stock Decrease',
        stockAdjustment: 'Stock Adjustment',
        stockMapped: 'Stock Mapped',
        stockRestored: 'Stock Restored',
        manualAdjustment: 'Manual Adjustment',
        system: 'System',
        na: 'N/A',
        clickToCheckProductions: 'Click to check productions',
        variantProductions: {
          title: 'Productions for Variant',
          variantId: 'Variant ID',
          productionId: 'Production #',
          orderId: 'Order ID',
          orderRef: 'Order Ref',
          quantity: 'Quantity',
          units: 'units',
          noProductions: 'No productions found for this variant',
          close: 'Close',
          status: {
            pending: 'Pending',
            pickup: 'Pickup',
            mapped: 'Mapped',
            completed: 'Completed',
            cancelled: 'Cancelled',
            unknown: 'Unknown',
          },
        },
      },
    },
    trackOrder: {
      title: 'Track Order',
      scanner: {
        httpsRequired: 'Camera requires HTTPS connection. Please use https://',
        notSupported: 'Camera not supported on this device/browser',
        permissionDenied:
          'Camera permission denied. Please allow camera access in your browser settings.',
        notFound: 'No camera found on this device',
        inUse: 'Camera is in use by another application',
        error: 'Camera error: {error}',
        startFailed: 'Cannot start camera: {error}',
        invalidQr: 'Invalid QR code format',
        tapToScan: 'Tap to scan QR code...',
      },
      status: {
        readySuccess: '✓ Design marked as ready successfully!',
        updateFailed: 'Failed to update status',
        rejectSuccess: '❌ Design rejected - Order returned to support',
        rejectFailed: 'Failed to reject design',
        rejectConfirm:
          '⚠️ Are you sure you want to REJECT this design?\n\nThis will:\n- Reset all workflow progress for this item\n- Restore the stock\n- Return the order to Support\n\nThis action cannot be undone.',
        uncheckError:
          'Cannot uncheck design that is already marked as ready',
        newOrder: 'New Order',
        inProduction: 'In Production',
        shipped: 'Shipped',
        delivered: 'Delivered',
        cancelled: 'Cancelled',
        closed: 'Closed',
        returnToSupport: 'Return to Support',
        cancelledOrder: '❌ Order Cancelled',
        closedOrder: '❌ Order Closed',
        markStaffReady: 'Mark Staff Ready',
        complete: '✓ Complete',
        waitingQC: '⏳ Waiting for QC',
        waitingPacking: '⏳ Waiting for Packing',
        waitingShipout: '⏳ Waiting for Shipout',
      },
      labels: {
        order: 'Order',
        user: 'User',
        orderStatus: 'Order Status',
        orderItems: 'Order Items',
        item: 'Item',
        variantId: 'Variant ID',
        styleSizeStock: 'STYLE - SIZE - STOCK',
        color: 'COLOR',
        designPositions: 'Design Positions',
        updating: 'Updating...',
        staff: 'Staff',
        qc: 'QC',
        pack: 'Pack',
        ship: 'Ship',
      },
      pes: {
        stitches: 'STITCHES',
        width: 'WIDTH',
        height: 'HEIGHT',
        colors: 'COLORS',
      },
      needle: {
        title: 'Needle Assignment (1-12) - {action}',
        dragOrTap: 'Drag or Tap to swap',
        tapToSwap: 'Tap another to swap',
        hint: '✓ Needle {needle} selected. Tap another needle to swap.',
      },
      colorSequence: {
        title: 'Color Stop Sequence',
        sequence: '#',
        needle: 'N#',
        color: 'COLOR',
        code: 'CODE',
        name: 'NAME',
        chart: 'CHART',
      },
      loading: 'Loading order information...',
      notFound: 'Order Not Found',
      failedLoad: 'Failed to load order information',
      tryAgain: 'Try Again',
      confirmModal: {
        markAsReady: 'Mark {position} as ready?',
        cancel: 'Cancel',
        confirm: 'Confirm',
      },
    },
  },
} satisfies Record<AppLocale, {
  language: {
    label: string
    vietnamese: string
    english: string
  }
  sidebar: {
    workspace: {
      teamName: string
      teamPlan: string
      general: string
      overview: string
      tasks: string
      apps: string
      users: string
      support: string
      helpCenter: string
      notifications: string
      settings: string
      profile: string
    }
    lemiex: {
      teamName: string
      teamPlan: string
      overview: string
      commerce: string
      operations: string
      supportTools: string
      administration: string
      dashboard: string
      welcome: string
      orders: string
      designs: string
      products: string
      catalog: string
      productVariants: string
      stores: string
      tickets: string
      stockManagement: string
      stockDashboard: string
      manageStock: string
      productions: string
      shortageReport: string
      shortageByVariant: string
      auditLogs: string
      hrPayroll: string
      attendances: string
      payrollReport: string
      salaryTiers: string
      embroideryProgress: string
      trackings: string
      videos: string
      wallets: string
      transactions: string
      pendingFund: string
      refunds: string
      surcharge: string
      debits: string
      quickAccess: {
        balance: string
        add: string
        orderIdPlaceholder: string
        openTrackPage: string
        scanQr: string
        scanUnavailable: string
        scanInvalid: string
        scanHttpsRequired: string
        scanNotSupported: string
        scanCameraDenied: string
        orderIdRequired: string
        addFundTitle: string
        addFundDescription: string
        transactionId: string
        generateTransactionId: string
        processing: string
        submit: string
        addFundPending: string
        addFundFailed: string
      }
      staffReport: string
      systems: string
      users: string
      permissions: string
      tiers: string
    }
  }
  command: {
    placeholder: string
    empty: string
    theme: string
    light: string
    dark: string
    system: string
  }
  profile: {
    manageProfile: string
    billing: string
    notifications: string
    signOut: string
    roleLabel: string
    signOutTitle: string
    signOutDesc: string
    cancel: string
  }
  pagination: {
    rowsPerPage: string
    pageOf: string
    goToFirstPage: string
    goToPreviousPage: string
    goToPage: string
    goToNextPage: string
    goToLastPage: string
  }
  orders: {
    title: string
    count: string
    refresh: string
    embroidery: string
    print: string
    loadErrorTitle: string
    empty: string
    noOrderIds: string
    copiedOrderIds: string
    noTrackingNumbers: string
    copiedTrackingNumbers: string
    copyTrackingFailed: string
    selectAtLeastOneOrder: string
    buyLabelFailed: string
    labelCreated: string
    labelJobsDispatched: string
    createOrder: string
    confirmBuyLabel: string
    confirmBuyLabelDesc: string
    confirmPurchase: string
    processing: string
    copyTracking: string
    buyLabel: string
    headers: {
      order: string
      seller: string
      ticket: string
      priority: string
      embType: string
      fulfillStatus: string
      items: string
      tracking: string
      printCost: string
      shipping: string
      totalCost: string
      payment: string
      created: string
      actions: string
    }
    status: {
      unknown: string
      noRefId: string
      noVariant: string
      hasTicket: string
      normal: string
      priority: string
      noItems: string
      itemCount: string
      noTracking: string
      label: string
      convert: string
      na: string
      unnamedItem: string
      front: string
    }
    actions: {
      view: string
      timeline: string
      edit: string
      support: string
      goToStores: string
      ticketExistsTitle: string
      ticketExistsDesc: string
      viewExistingTickets: string
      createNewTicket: string
      pending: string
      remakeDesign: string
      remakeQr: string
    }
    timelineModal: {
      title: string
      orderPrefix: string
      loading: string
      empty: string
      loadError: string
      close: string
      columns: {
        action: string
        description: string
        createdAt: string
        updatedAt: string
      }
    }
    detail: {
      backToOrders: string
      loadingOrder: string
      orderNotFound: string
      orderInfo: string
      sellerInfo: string
      shippingInfo: string
      orderItems: string
      pricing: string
      actionsTitle: string
      orderStt: string
      referenceId: string
      sellerRef: string
      paymentStatus: string
      createdAt: string
      username: string
      email: string
      tier: string
      store: string
      service: string
      method: string
      trackingId: string
      address: string
      shippingLabel: string
      viewLabel: string
      convertLabel: string
      viewConvert: string
      qrCodes: string
      download: string
      downloadAll: string
      downloadingAll: string
      downloadAllSuccess: string
      mergedImages: string
      quantity: string
      printCost: string
      shippingCost: string
      extraFee: string
      refundFee: string
      totalCost: string
      profitMargin: string
      updatingLabel: string
      updateLabel: string
      updateLabelSuccess: string
      updateLabelFailed: string
      cancelOrder: string
      sellerCancelConfirm: string
      sellerCancelSuccess: string
      sellerCancelFailed: string
      videos: string
      noData: string
    }
    createOrderDialog: {
      storeRequiredTitle: string
      storeRequiredDesc: string
      categoryTitle: string
      categoryDesc: string
      embroideryTitle: string
      embroideryDesc: string
      tumblerTitle: string
      tumblerDesc: string
      typeTitle: string
      typeDescEmbroidery: string
      typeDescTumbler: string
      noDesignTitle: string
      noDesignDesc: string
      labelShipTitle: string
      labelShipDesc: string
      sellerShipTitle: string
      sellerShipDesc: string
      tumblerLabelShipTitle: string
      tumblerLabelShipDesc: string
      tumblerSellerShipTitle: string
      tumblerSellerShipDesc: string
    }
    createForm: {
      labelShipTitle: string
      labelShipSubtitle: string
      sellerShipTitle: string
      sellerShipSubtitle: string
      backToOrders: string
      orderInformation: string
      shippingInformation: string
      shippingAddress: string
      productsAndDesignFiles: string
      productsAndDesignFilesDesc: string
      orderReferenceId: string
      storeApiKey: string
      sellerReference: string
      orderStatus: string
      shippingMethod: string
      shippingService: string
      fulfillmentPriority: string
      shippingLabelUrl: string
      shippingLabelHint: string
      orderNotes: string
      recipientName: string
      phoneNumber: string
      streetAddress: string
      apartmentSuite: string
      city: string
      stateProvince: string
      zipCode: string
      country: string
      productCardTitle: string
      productCardDesc: string
      productVariant: string
      variantId: string
      quantity: string
      productName: string
      mockupFrontUrl: string
      mockupBackUrl: string
      mockupPreview: string
      addFrontMockupUrl: string
      designFiles: string
      designFilesDesc: string
      addDesignSide: string
      designTitle: string
      position: string
      designFileUrl: string
      addProduct: string
      remove: string
      cancel: string
      createOrder: string
      creating: string
      loadingStores: string
      selectedStore: string
      storesAvailable: string
      noStoresFound: string
      standardShippingMethod: string
      fixedUsps: string
      optionLabels: {
        orderStatus: {
          new_order: string
          on_hold: string
          confirm: string
          test_order: string
        }
        shippingService: {
          USPS: string
          UPS: string
          FedEx: string
        }
        country: {
          US: string
          CA: string
          GB: string
          AU: string
          DE: string
          FR: string
          JP: string
          VN: string
        }
        designPosition: {
          front: string
          back: string
          neck: string
        }
      }
      productPicker: {
        product: string
        size: string
        loadingProducts: string
        selectProduct: string
        loadingSizes: string
        selectSize: string
        resolvingVariant: string
        variantId: string
        chooseAll: string
      }
      upload: {
        upload: string
        uploading: string
        uploadFailed: string
        uploadImageOrPaste: string
        previewAlt: string
      }
      placeholders: {
        orderRefId: string
        manualApiKey: string
        sellerRef: string
        selectStore: string
        selectStatus: string
        selectShippingMethod: string
        selectShippingService: string
        selectPriority: string
        shippingLabel: string
        notes: string
        recipientName: string
        phone: string
        street1: string
        street2: string
        city: string
        state: string
        zip: string
        selectCountry: string
        variantId: string
        productName: string
        mockupFront: string
        mockupBack: string
        selectPosition: string
        designFileUrl: string
      }
      validation: {
        orderRefRequired: string
        apiKeyRequired: string
        shippingLabelRequired: string
        shippingAddressRequired: string
        variantRequired: string
        productNameRequired: string
        mockupRequired: string
        designFileRequired: string
      }
      submit: {
        successWithId: string
        success: string
        failed: string
      }
    }
    editForm: {
      title: string
      reference: string
      loading: string
      loadingFailed: string
      cannotEdit: string
      sellerBlockReason: string
      generalInformation: string
      shippingDetails: string
      addressInformation: string
      orderItems: string
      note: string
      shippingMethod: string
      shippingService: string
      shippingLabelUrl: string
      addressLine1: string
      addressLine2: string
      fullName: string
      city: string
      state: string
      zipCode: string
      country: string
      phone: string
      mockupImages: string
      frontViewUrl: string
      backViewUrl: string
      printFilesDesigns: string
      addPosition: string
      noPrintFiles: string
      positionPlaceholder: string
      url: string
      imageUrl: string
      pdfUrl: string
      embUrl: string
      pesUrl: string
      cancel: string
      saveChanges: string
      saving: string
      saveSuccess: string
      noChanges: string
      saveFailed: string
      viewFile: string
      changeVariant: string
      currentVariant: string
      newVariant: string
      variantChangeLocked: string
      revertVariant: string
      variantChangedHint: string
    }
    filters: {
      orderId: string
      variantId: string
      refId: string
      trackingNumber: string
      search: string
      clearAll: string
      getIds: string
      filters: string
      excludeStatus: string
      shippingInfo: string
      missingShippingInfo: string
      fulfillStatus: string
      paymentStatus: string
      productAttributes: string
      style: string
      color: string
      size: string
      seller: string
      embType: string
      productName: string
      dateFrom: string
      dateTo: string
      shippedDateRange: string
      shippedDateFrom: string
      shippedDateTo: string
      shippedToday: string
      shippedDateHint: string
      sortBy: string
      sortOrder: string
      placeholders: {
        orderId: string
        variantId: string
        refId: string
        trackingNumber: string
        selectStyle: string
        selectColor: string
        selectSize: string
        allSellers: string
        allTypes: string
        productName: string
        createdDate: string
        ascending: string
      }
      selectStyle: string
      selectColor: string
      selectSize: string
      allSellers: string
      allTypes: string
    }
    paymentStatuses: {
      pending: string
      paid: string
      partial_refund: string
      refunded: string
      failed: string
    }
    fulfillStatuses: {
      new_order: string
      confirm: string
      pending_stock: string
      in_stock: string
      producing: string
      qc_pass: string
      packed: string
      shipped: string
      on_hold: string
      return_to_support: string
      cancelled: string
      cancelled_refund_shipping: string
      closed: string
      test_order: string
    }
    sortBy: {
      created_at: string
      updated_at: string
      shipped_at: string
      id: string
      ref_id: string
    }
    sortOrder: {
      asc: string
      desc: string
    }
  }
  productVariants: {
    title: string
    count: string
    loading: string
    loadError: string
    empty: string
    tabs: {
      embroidery: string
      print: string
    }
    columns: {
      product: string
      templateUrl: string
      colors: string
      sizes: string
      variants: string
      totalStock: string
      priceRange: string
      status: string
      actions: string
    }
    filters: {
      search: string
      searchPlaceholder: string
      style: string
      stylePlaceholder: string
      brand: string
      brandPlaceholder: string
      status: string
      allStatus: string
      sortBy: string
      newestFirst: string
      oldestFirst: string
      nameAz: string
      nameZa: string
      brandAz: string
      brandZa: string
      clearFilters: string
    }
    status: {
      noBrand: string
      noStyle: string
      noTemplate: string
      noColors: string
      noSizes: string
      active: string
      activeLabel: string
      inactiveLabel: string
      na: string
      to: string
    }
    actions: {
      importCsv: string
      createProduct: string
      importPending: string
      stock: string
      view: string
      delete: string
      confirmDelete: string
      deleteSuccess: string
      deleteFailed: string
      deletePending: string
    }
    importDialog: {
      title: string
      description: string
      downloadTemplate: string
      downloadCurrentData: string
      clickToSelect: string
      orDragDrop: string
      selectCsvFile: string
      preview: string
      previewFailed: string
      import: string
      importSuccess: string
      importFailed: string
      products: string
      newProducts: string
      existingProducts: string
      newTag: string
      updateTag: string
      imported: string
      failed: string
      errors: string
      done: string
    }
    stockDialog: {
      title: string
      description: string
      addStock: string
      subtractStock: string
      color: string
      size: string
      quantity: string
      quantityPlaceholder: string
      selectColor: string
      selectSize: string
      validation: string
      updating: string
      updateFailed: string
      addSuccess: string
      subtractSuccess: string
    }
    detail: {
      loading: string
      loadError: string
      notFound: string
      back: string
      active: string
      inactive: string
      brand: string
      style: string
      warehouse: string
      category: string
      print: string
      embroidery: string
      created: string
      updated: string
      editProduct: string
      totalVariants: string
      totalStock: string
      priceRange: string
      colors: string
      sizes: string
      variantsTitle: string
      variantsCount: string
      noData: string
      save: string
      cancel: string
      edit: string
      delete: string
      confirmDeleteVariant: string
      deleteVariantSuccess: string
      deleteVariantFailed: string
      deletePending: string
      variantUpdated: string
      updateFailed: string
      pricingSaved: string
      viewPricing: string
      setPricing: string
      pricing: {
        title: string
        noVariant: string
        readOnly: string
        production: string
        shipping: string
        type: string
        close: string
        cancel: string
        saving: string
        save: string
        failed: string
      }
      columns: {
        variantId: string
        color: string
        size: string
        stock: string
        supplierPrice: string
        tierPricing: string
        weight: string
        dimensions: string
        status: string
        actions: string
      }
    }
    createForm: {
      title: string
      description: string
      productInfo: string
      variants: string
      pricing: string
      productName: string
      style: string
      brand: string
      warehouse: string
      productNamePlaceholder: string
      stylePlaceholder: string
      brandPlaceholder: string
      warehousePlaceholder: string
      mockupUrl: string
      category: string
      status: string
      active: string
      inactive: string
      addVariant: string
      noVariantsYet: string
      variant: string
      removeVariant: string
      variantId: string
      variantIdPlaceholder: string
      sku: string
      skuPlaceholder: string
      color: string
      colorPlaceholder: string
      size: string
      sizePlaceholder: string
      stock: string
      supplierPrice: string
      weight: string
      dimensions: string
      addPrice: string
      noPricesAdded: string
      tier: string
      priceType: string
      price: string
      cancel: string
      create: string
      creating: string
      productNameRequired: string
      variantIdRequired: string
      createSuccess: string
      createFailed: string
    }
  }
  storesPage: {
    title: string
    subtitle: string
    totalStores: string
    addStore: string
    searchPlaceholder: string
    allStatus: string
    loading: string
    noStores: string
    noStoresAvailable: string
    failedToLoad: string
    columns: {
      id: string
      user: string
      storeName: string
      status: string
      createdAt: string
      actions: string
    }
    status: {
      active: string
      unconfirmed: string
      banned: string
    }
    dialog: {
      createTitle: string
      createSubtitle: string
      editTitle: string
      editSubtitle: string
      loadingUsers: string
      loadingStore: string
      user: string
      selectUser: string
      storeName: string
      enterStoreName: string
      apiKey: string
      status: string
      cancel: string
      create: string
      creating: string
      update: string
      updating: string
      onlySelf: string
      onlyAdmin: string
      statusHint: string
      apiKeyHint: string
      apiKeyEditHint: string
      refreshKey: string
      successCreate: string
      successUpdate: string
      failedCreate: string
      failedUpdate: string
      failedLoadUsers: string
      failedLoadStore: string
      validation: {
        requiredUser: string
        requiredName: string
        requiredApiKey: string
      }
      active: string
      unconfirmed: string
      banned: string
    }
  }
  usersPage: {
    title: string
    addFund: string
    addNew: string
    backToList: string
    backToDetail: string
    createTitle: string
    editTitle: string
    viewTitle: string
    accountInfo: string
    userDetails: string
    integrationSettings: string
    debitSettings: string
    additionalOptions: string
    username: string
    email: string
    role: string
    statusLabel: string
    registrationDate: string
    firstName: string
    lastName: string
    phone: string
    dob: string
    address: string
    webhookUrl: string
    telegramId: string
    apiKey: string
    maxDebit: string
    maxDateDebit: string
    minDateDebit: string
    balanceLabel: string
    supportUs: string
    privateSeller: string
    days: string
    yes: string
    no: string
    filters: {
      search: string
      allStatus: string
      allRoles: string
      allTiers: string
    }
    status: {
      active: string
      unconfirmed: string
      banned: string
    }
    columns: {
      username: string
      fullName: string
      role: string
      email: string
      balance: string
      tier: string
      registrationDate: string
      status: string
      actions: string
    }
    form: {
      accountInfo: string
      userDetails: string
      integrationSettings: string
      debitSettings: string
      additionalOptions: string
      email: string
      username: string
      password: string
      confirmPassword: string
      newPassword: string
      confirmNewPassword: string
      leaveBlank: string
      role: string
      status: string
      firstName: string
      lastName: string
      phone: string
      dob: string
      address: string
      webhookUrl: string
      telegramId: string
      apiKey: string
      maxDebit: string
      maxDateDebit: string
      minDateDebit: string
      supportUs: string
      yes: string
      no: string
      optional: string
      loadingRoles: string
      noRoles: string
      submit: string
      update: string
      cancel: string
    }
    addFundModal: {
      title: string
      selectSeller: string
      loadingSellers: string
      selectPlaceholder: string
      currentBalance: string
      type: string
      deposit: string
      withdraw: string
      amount: string
      enterAmount: string
      note: string
      notePlaceholder: string
      newBalance: string
      cancel: string
      submit: string
      selectSellerRequired: string
      invalidAmount: string
      fundFailed: string
      fundSuccess: string
    }
    tiers: {
      silver: string
      gold: string
      platinum: string
      diamond: string
    }
    roles: {
      admin: string
      seller: string
      user: string
      supplier: string
      staff: string
      support: string
      designer: string
      finance: string
    }
    notFound: string
    loadFailed: string
    deleteConfirm: string
    deleteSuccess: string
    deleteFailed: string
    createSuccess: string
    updateSuccess: string
    loading: string
    deleteTitle: string
    error: string
    na: string
  }
  permissionsPage: {
    title: string
    subtitle: string
    syncPermissions: string
    syncing: string
    permission: string
    save: string
    saving: string
    adminHasAllPermissions: string
    savePermissions: string
    selectAllInGroup: string
    noPermissions: string
    loadFailed: string
    saveSuccess: string
    saveFailed: string
    syncSuccess: string
    syncFailed: string
    otherGroup: string
    createRole: string
    newRoleTitle: string
    newRoleDescription: string
    roleName: string
    roleNamePlaceholder: string
    roleDisplayName: string
    roleDisplayNamePlaceholder: string
    roleDescription: string
    roleDescriptionPlaceholder: string
    cancel: string
    create: string
    creating: string
    createSuccess: string
    createFailed: string
    deleteRole: string
    confirmDelete: string
    builtInRole: string
    deleteSuccess: string
    deleteFailed: string
  }
  tiersPage: {
    title: string
    createTier: string
    loading: string
    noTiers: string
    tierBadge: string
    extraFees: string
    refundFees: string
    embroideryFees: string
    priorityFees: string
    addExtraFee: string
    addRefundFee: string
    addEmbroideryFee: string
    addPriorityFee: string
    emptyExtraFees: string
    emptyRefundFees: string
    emptyEmbroideryFees: string
    emptyPriorityFees: string
    minStitch: string
    maxStitch: string
    amount: string
    stitch: string
    type: string
    name: string
    displayName: string
    description: string
    price: string
    actions: string
    edit: string
    delete: string
    createTitle: string
    editTitle: string
    tierName: string
    tierNamePlaceholder: string
    save: string
    cancel: string
    creating: string
    saving: string
    deleting: string
    confirmDeleteTitle: string
    confirmDeleteDescription: string
    extraFeeDialogTitle: string
    refundFeeDialogTitle: string
    embroideryFeeDialogTitle: string
    priorityFeeDialogTitle: string
    embroideryType: string
    embroideryTypePlaceholder: string
    priorityName: string
    priorityDisplayNamePlaceholder: string
    priorityDescriptionPlaceholder: string
    standard: string
    metallic: string
    glow: string
    puff: string
    normalPriority: string
    rushPriority: string
    requiredTierName: string
    requiredFields: string
    tierCreated: string
    tierUpdated: string
    tierDeleted: string
    feeCreated: string
    feeUpdated: string
    feeDeleted: string
    failedLoad: string
    failedCreateTier: string
    failedUpdateTier: string
    failedDeleteTier: string
    failedSaveFee: string
    failedDeleteFee: string
  }
  dashboardPage: {
    title: string
    subtitle: string
    loading: string
    failedLoad: string
    timeRangeLabel: string
    today: string
    yesterday: string
    last7Days: string
    last30Days: string
    last90Days: string
    lastYear: string
    sellerScope: string
    sellerScopeDescription: string
    totalOrders: string
    totalRevenue: string
    productsVariants: string
    totalStock: string
    ordersThisPeriod: string
    revenueThisPeriod: string
    variants: string
    lowStockWarning: string
    totalDeposits: string
    totalWithdrawals: string
    totalPayments: string
    pendingTransactions: string
    transactionsThisPeriod: string
    productSalesQuantity: string
    top5Products: string
    revenueByPaymentStatus: string
    dailyBreakdown: string
    dailyOrders: string
    ordersPerDay: string
    transactionsOverview: string
    dailyTransactions: string
    noSalesData: string
    noRevenueData: string
    noOrderData: string
    noTransactionData: string
    ordersByPaymentStatus: string
    ordersByFulfillStatus: string
    topProducts: string
    recentOrders: string
    noRecentOrders: string
    noTopProducts: string
    orderId: string
    store: string
    items: string
    paymentStatus: string
    fulfillStatus: string
    created: string
    viewAll: string
    vsPrevious: string
    empty: string
    units: string
    ordersTotalRow: string
    ordersShippingRow: string
    ordersDeliveredRow: string
    ordersOnHoldRow: string
    revenueTotalRow: string
    revenuePeriodRow: string
    revenuePaidRow: string
    revenuePendingRow: string
    productsStockTitle: string
    productsRow: string
    variantsRow: string
    stockRow: string
    lowStockRow: string
    financialsTitle: string
    depositsRow: string
    withdrawalsRow: string
    paymentsRow: string
    txPeriodRow: string
    paymentBreakdownTitle: string
    ordersUnit: string
    rankingProductsTitle: string
    rankingSellersTitle: string
    rankingUpdated: string
    rankCol: string
    productNameCol: string
    soldQtyCol: string
    sellerNameCol: string
    totalItemsCol: string
    noSellerData: string
    funnelCellSize: string
    flowNewOrder: string
    flowConfirmed: string
    flowProducing: string
    flowShipped: string
    shopStatsTitle: string
    shopColIndex: string
    shopColName: string
    shopColTotal: string
    shopColRefund: string
    shopColPaid: string
    shopColProcessing: string
    shopColOnHold: string
    shopColSellers: string
    noShopData: string
  }
  staffReportPage: {
    title: string
    subtitle: string
    filters: {
      dateFrom: string
      dateTo: string
      staffMember: string
      allStaff: string
      apply: string
      refresh: string
    }
    summary: {
      title: string
      staffName: string
      username: string
      itemsProcessed: string
      contribution: string
      share: string
      noData: string
      total: string
      items: string
    }
    details: {
      title: string
      staffName: string
      username: string
      orderItem: string
      order: string
      item: string
      metaKey: string
      processedAt: string
      noData: string
    }
    loading: string
    failedLoadList: string
    failedLoadReport: string
  }
  attendancesPage: {
    title: string
    subtitle: string
    importBtn: string
    importing: string
    filters: {
      employeeName: string
      searchPlaceholder: string
      customRange: string
      from: string
      to: string
      date: string
      month: string
      clear: string
    }
    columns: {
      id: string
      employeeName: string
      totalDays: string
      week: string
      month: string
      year: string
    }
    days: string
    logs: {
      show: string
      entries: string
      showing: string
      of: string
      records: string
      noRecords: string
      date: string
      checkIn: string
      checkOut: string
      totalWork: string
      loading: string
      noRecordsFound: string
      completeMissing: string
      previous: string
      next: string
      pageOf: string
    }
    editModal: {
      title: string
      employee: string
      workDate: string
      existingTime: string
      missingType: string
      checkIn: string
      checkOut: string
      time: string
      cancel: string
      save: string
      saving: string
      validation: {
        timeRequired: string
      }
    }
    messages: {
      failedLoadData: string
      failedLoadLogs: string
      importSuccess: string
      importFailed: string
      noRecords: string
      updateSuccess: string
      updateFailed: string
    }
  }
  payrollPage: {
    title: string
    subtitle: string
    setRate: string
    rewardsPenalties: string
    month: string
    customRange: string
    from: string
    to: string
    totalHours: string
    totalSalary: string
    netTotal: string
    companyTaxTotal: string
    missingRate: string
    staffs: string
    noEmployees: string
    employee: string
    rateHr: string
    hours: string
    adjustments: string
    grossSalary: string
    netSalary: string
    companyTax: string
    totalSalaryCol: string
    actions: string
    edit: string
    log: string
    view: string
    clickToEdit: string
    save: string
    cancel: string
    close: string
    loading: string
    selectEmployee: string
    selectTierOrRate: string
    fillTypeAmount: string
    rateSetSuccess: string
    failedSetRate: string
    rateUpdated: string
    failedUpdateRate: string
    adjustmentSuccess: string
    failedAdjustment: string
    failedLoadPayroll: string
    fieldUpdated: string
    failedUpdate: string
    setRateModal: {
      title: string
      selectEmployees: string
      selectAll: string
      selected: string
      selectTier: string
      or: string
      customRate: string
      effectiveFrom: string
      setting: string
      setRateBtn: string
    }
    editRateModal: {
      title: string
      hourlyRate: string
      detachNote: string
      note: string
      reasonPlaceholder: string
      saving: string
    }
    salaryLog: {
      title: string
      noHistory: string
      custom: string
      from: string
      ended: string
      current: string
    }
    adjustmentModal: {
      title: string
      type: string
      typePlaceholder: string
      amount: string
      action: string
      addReward: string
      deductPenalty: string
      date: string
      processing: string
      add: string
      deduct: string
    }
    adjustmentDetail: {
      title: string
      noAdjustments: string
      typeReason: string
    }
    guide: {
      title: string
      close: string
      steps: Array<{
        icon: string
        title: string
        desc: string
      }>
    }
  }
  payrollTiersPage: {
    title: string
    subtitle: string
    createTier: string
    tierName: string
    hourlyRate: string
    currency: string
    description: string
    actions: string
    noTiers: string
    createTitle: string
    editTitle: string
    deleteTitle: string
    namePlaceholder: string
    ratePlaceholder: string
    descriptionPlaceholder: string
    create: string
    creating: string
    save: string
    saving: string
    cancel: string
    delete: string
    deleting: string
    confirmDelete: string
    fillTypeAmount: string
    tierCreated: string
    tierUpdated: string
    tierDeleted: string
    failedLoadTiers: string
    failedCreateTier: string
    failedUpdateTier: string
    failedDeleteTier: string
  }
  ticketsPage: {
    title: string
    subtitle: string
    totalTickets: string
    tabs: {
      all: string
      new: string
      solved: string
    }
    filters: {
      ticketId: string
      orderId: string
      subject: string
      allSellers: string
      allSupport: string
    }
    columns: {
      id: string
      orderId: string
      subject: string
      status: string
      userReply: string
      lastReply: string
      owner: string
      updated: string
      actions: string
    }
    status: {
      new: string
      solved: string
    }
    actions: {
      view: string
      solve: string
    }
    noTicketsTitle: string
    noTicketsDescriptionFiltered: string
    noTicketsDescriptionEmpty: string
    loadFailed: string
    statusUpdated: string
    statusUpdateFailed: string
    createSuccess: string
    createDialog: {
      createTitle: string
      subject: string
      subjectPlaceholder: string
      message: string
      messagePlaceholder: string
      attachFile: string
      clickToUpload: string
      fileHint: string
      cancel: string
      creating: string
      createNew: string
      subjectRequired: string
      messageRequired: string
      orderIdMissing: string
      fileSizeError: string
      fileTypeError: string
      createFailed: string
    }
  }
  ticketDetailPage: {
    back: string
    backToTickets: string
    loading: string
    notFound: string
    loadDetailFailed: string
    fileSizeError: string
    fileTypeError: string
    viewPdf: string
    noMessages: string
    placeholder: string
    placeholderImage: string
    enterMessage: string
    sendFailed: string
    statusUpdated: string
    statusUpdateFailed: string
    markSolved: string
    reopen: string
    remove: string
    status: {
      new: string
      solved: string
    }
    unknown: string
  }
  walletTransactionsPage: {
    title: string
    subtitle: string
    totalTransactions: string
    exportAll: string
    exportPayments: string
    exportDeposits: string
    exportRefunds: string
    tabs: {
      all: string
      payments: string
      deposits: string
      refunds: string
    }
    filters: {
      allSellers: string
      fromDate: string
      toDate: string
      search: string
    }
    columns: {
      id: string
      transactionId: string
      seller: string
      orderId: string
      store: string
      type: string
      amount: string
      balance: string
      note: string
      status: string
      date: string
    }
    status: {
      completed: string
      pending: string
      failed: string
    }
    type: {
      add_fund: string
      order_payment: string
      refund: string
    }
    summary: {
      total: string
      page: string
    }
    loading: string
    noTransactionsTitle: string
    noTransactionsDescriptionFiltered: string
    noTransactionsDescriptionEmpty: string
    loadFailed: string
    loadSellersFailed: string
    exporting: string
    exportSuccess: string
    exportFailed: string
    na: string
    none: string
  }
  pendingFundPage: {
    title: string
    subtitle: string
    showing: string
    loading: string
    noRequests: string
    allCaught: string
    fetchError: string
    confirmApprove: string
    approveSuccess: string
    approveFailed: string
    rejectSuccess: string
    rejectFailed: string
    approve: string
    reject: string
    columns: {
      id: string
      seller: string
      type: string
      amount: string
      transactionId: string
      note: string
      date: string
      actions: string
    }
    rejectModal: {
      title: string
      subtitle: string
      placeholder: string
      cancel: string
      confirm: string
    }
    type: {
      deposit: string
      refund: string
    }
    na: string
  }
  partnerAppsPage: {
    title: string
    subtitle: string
    addApp: string
    loading: string
    empty: string
    copied: string
    noAuthLink: string
    na: string
    columns: {
      name: string
      linkAuth: string
      proxyStatus: string
      status: string
      actions: string
    }
    copyLink: string
    edit: string
    dialog: {
      createTitle: string
      editTitle: string
      name: string
      slug: string
      authUrl: string
      proxyStatus: string
      status: string
      cancel: string
      create: string
      update: string
      successCreate: string
      successUpdate: string
    }
  }
  partnerStoresPage: {
    title: string
    subtitle: string
    addStore: string
    searchPlaceholder: string
    loading: string
    empty: string
    failed: string
    syncTitle: string
    syncDescription: string
    syncConfirm: string
    syncCancel: string
    syncProgressTitle: string
    syncProgressDescription: string
    syncDone: string
    na: string
    columns: {
      id: string
      partner: string
      name: string
      user: string
      status: string
      totalOrders: string
      accountNo: string
      actions: string
    }
    dialog: {
      createTitle: string
      editTitle: string
      storeName: string
      storeCode: string
      user: string
      partnerApp: string
      status: string
      accountNo: string
      cancel: string
      create: string
      update: string
      successCreate: string
      successUpdate: string
      na: string
    }
  }
  partnerSyncOrdersPage: {
    title: string
    subtitle: string
    loading: string
    empty: string
    filters: {
      store: string
      orderNo: string
      status: string
      fulfillment: string
      allStores: string
      allStatuses: string
      allFulfillment: string
      orderNoPlaceholder: string
      search: string
      clearAll: string
      pending: string
      paid: string
      cancelled: string
      noFulfillment: string
      ready: string
      shipped: string
    }
    columns: {
      id: string
      store: string
      customer: string
      user: string
      partnerOrder: string
      tracking: string
      items: string
      discount: string
      total: string
      status: string
      fulfillment: string
      note: string
      actions: string
    }
    labels: {
      sku: string
      qty: string
      buyLabel: string
      buyLabels: string
      edit: string
      ship: string
      delete: string
    }
  }
  stock: {
    manage: {
      title: string
      description: string
      importExport: string
      loading: string
      loadError: string
      summary: {
        totalStock: string
        reserved: string
        available: string
        lowStockItems: string
      }
      filters: {
        variantId: string
        sku: string
        style: string
        color: string
        size: string
        stockLevel: string
        status: string
        searchPlaceholder: string
        allStyles: string
        allColors: string
        allSizes: string
        all: string
        lowStock: string
        outOfStock: string
        active: string
        inactive: string
        reset: string
      }
      empty: {
        title: string
        description: string
      }
      tabs: {
        variants: string
      }
      bulk: {
        selected: string
        hint: string
        clearSelection: string
        operation: string
        selectOperation: string
        stockOperations: string
        statusOperations: string
        addStock: string
        subtractStock: string
        setStock: string
        activate: string
        deactivate: string
        amountToAdd: string
        amountToSubtract: string
        newStockLevel: string
        enterValue: string
        reason: string
        reasonPlaceholder: string
        applyTo: string
        selectVariantsAndAction: string
        enterValidStock: string
        success: string
      }
      table: {
        variantId: string
        sku: string
        style: string
        color: string
        size: string
        stock: string
        reserved: string
        available: string
        active: string
        actions: string
        save: string
        cancel: string
        edit: string
        history: string
        noVariants: string
        stockCannotBeNegative: string
        noChangesToSave: string
        variantUpdated: string
        updateFailed: string
        variantStatusUpdated: string
      }
      historyDialog: {
        title: string
        currentStock: string
        loading: string
        noRecords: string
        increase: string
        decrease: string
        adjust: string
        import: string
        skuUpdated: string
        styleUpdated: string
        activated: string
        deactivated: string
        bulkUpdate: string
        bulkOperation: string
        operation: string
        showingLast: string
        sku: string
        style: string
        active: string
        empty: string
        variantId: string
      }
      importExportDialog: {
        title: string
        import: string
        export: string
        importInstructions: string
        instructionFile: string
        instructionId: string
        instructionFields: string
        instructionUpdate: string
        stockOperationType: string
        setStock: string
        addStock: string
        subtractStock: string
        hintSet: string
        hintAdd: string
        hintSubtract: string
        selectCsvFile: string
        chooseFile: string
        downloadTemplate: string
        skuImport: string
        variantImport: string
        fullImport: string
        skuTemplateHint: string
        variantTemplateHint: string
        fullTemplateHint: string
        importing: string
        importBtn: string
        importResults: string
        success: string
        failed: string
        errors: string
        moreErrors: string
        exportStockData: string
        exportDesc: string
        exportFields1: string
        exportFields2: string
        exportFields3: string
        exportFields4: string
        exportPreview1: string
        exportPreview2: string
        exporting: string
        exportToCsv: string
        pleaseSelectCsv: string
        pleaseSelectFile: string
        importSuccess: string
        importFailed: string
        failedToImport: string
        exportSuccess: string
        exportFailed: string
        failedToExport: string
      }
    }
    shortage: {
      title: string
      subtitleWithCount: string
      subtitleAllGood: string
      viewByVariant: string
      exportCsv: string
      exporting: string
      exportSuccess: string
      exportFailed: string
      failedToLoadReport: string
      loading: string
      noPendingOrders: string
      totalPendingOrders: string
      ordersWithShortage: string
      variantsAffected: string
      totalShortage: string
      searchOrder: string
      orderIdRefIdPlaceholder: string
      searchVariant: string
      variantIdPlaceholder: string
      pendingReason: string
      fromDate: string
      toDate: string
      sortBy: string
      orderId: string
      refId: string
      seller: string
      items: string
      shortage: string
      daysPending: string
      action: string
      view: string
      day: string
      days: string
      awaitingProcessing: string
      awaitingProcessingDesc: string
      missingFiles: string
      noItems: string
      noItemsDesc: string
      unknownReason: string
      unknownReasonDesc: string
      status: {
        shortage: string
        missing_files: string
        awaiting_allocation: string
        no_items: string
        unknown: string
      }
      sortOptions: {
        seller_username: string
        days_pending: string
        shortage: string
        created_at: string
      }
      variantTable: {
        title: string
        noVariants: string
        variantId: string
        style: string
        color: string
        size: string
        stock: string
        demand: string
        shortage: string
      }
    }
    shortageByVariant: {
      title: string
      subtitleWithCount: string
      subtitleAllGood: string
      viewByOrder: string
      failedToLoad: string
      totalVariants: string
      totalShortage: string
      ordersAffected: string
      searchVariant: string
      variantIdPlaceholder: string
      style: string
      stylePlaceholder: string
      fromDate: string
      toDate: string
      sortBy: string
      loading: string
      noShortage: string
      noShortageDesc: string
      variantId: string
      color: string
      size: string
      stock: string
      demand: string
      shortage: string
      orders: string
      day: string
      days: string
      sortOptions: {
        shortage: string
        orders_count: string
        demand: string
        variant_id: string
      }
      ordersTable: {
        title: string
        noOrders: string
        orderId: string
        refId: string
        seller: string
        quantity: string
        shortage: string
        daysPending: string
        action: string
        view: string
      }
    }
    auditLogs: {
      title: string
      subtitle: string
      loading: string
      noLogs: string
      failedToLoadLogs: string
      failedToLoadOptions: string
      failedToCheckProductions: string
      searchVariant: string
      enterVariantId: string
      style: string
      allStyles: string
      color: string
      allColors: string
      size: string
      allSizes: string
      action: string
      allActions: string
      orderId: string
      enterOrderId: string
      dateFrom: string
      dateTo: string
      dateTime: string
      user: string
      product: string
      before: string
      after: string
      change: string
      reason: string
      stockIncrease: string
      stockDecrease: string
      stockAdjustment: string
      stockMapped: string
      stockRestored: string
      manualAdjustment: string
      system: string
      na: string
      clickToCheckProductions: string
      variantProductions: {
        title: string
        variantId: string
        productionId: string
        orderId: string
        orderRef: string
        quantity: string
        units: string
        noProductions: string
        close: string
        status: {
          pending: string
          pickup: string
          mapped: string
          completed: string
          cancelled: string
          unknown: string
        }
      }
    }
  }
  trackOrder: {
    title: string
    scanner: {
      httpsRequired: string
      notSupported: string
      permissionDenied: string
      notFound: string
      inUse: string
      error: string
      startFailed: string
      invalidQr: string
      tapToScan: string
    }
    status: {
      readySuccess: string
      updateFailed: string
      rejectSuccess: string
      rejectFailed: string
      rejectConfirm: string
      uncheckError: string
      newOrder: string
      inProduction: string
      shipped: string
      delivered: string
      cancelled: string
      closed: string
      returnToSupport: string
      cancelledOrder: string
      closedOrder: string
      markStaffReady: string
      complete: string
      waitingQC: string
      waitingPacking: string
      waitingShipout: string
    }
    labels: {
      order: string
      user: string
      orderStatus: string
      orderItems: string
      item: string
      variantId: string
      styleSizeStock: string
      color: string
      designPositions: string
      updating: string
      staff: string
      qc: string
      pack: string
      ship: string
    }
    pes: {
      stitches: string
      width: string
      height: string
      colors: string
    }
    needle: {
      title: string
      dragOrTap: string
      tapToSwap: string
      hint: string
    }
    colorSequence: {
      title: string
      sequence: string
      needle: string
      color: string
      code: string
      name: string
      chart: string
    }
    loading: string
    notFound: string
    failedLoad: string
    tryAgain: string
    confirmModal: {
      markAsReady: string
      cancel: string
      confirm: string
    }
  }
}>

type I18nContextType = {
  locale: AppLocale
  setLocale: (locale: AppLocale) => void
  messages: (typeof uiMessages)[AppLocale]
}

const I18nContext = createContext<I18nContextType | null>(null)

export function I18nProvider({ children }: { children: React.ReactNode }) {
  const [locale, setLocaleState] = useState<AppLocale>('vi')

  useEffect(() => {
    queueMicrotask(() => {
      const savedLocale = window.localStorage.getItem(
        LOCALE_STORAGE_KEY
      ) as AppLocale | null

      if (savedLocale === 'vi' || savedLocale === 'en') {
        setLocaleState(savedLocale)
        return
      }

      const browserLocale = navigator.language.toLowerCase()
      if (browserLocale.startsWith('en')) {
        setLocaleState('en')
      }
    })
  }, [])

  const setLocale = (nextLocale: AppLocale) => {
    setLocaleState(nextLocale)
    window.localStorage.setItem(LOCALE_STORAGE_KEY, nextLocale)
  }

  const value = useMemo<I18nContextType>(
    () => ({
      locale,
      setLocale,
      messages: uiMessages[locale],
    }),
    [locale]
  )

  return <I18nContext value={value}>{children}</I18nContext>
}

export function useI18n() {
  const context = useContext(I18nContext)

  if (!context) {
    throw new Error('useI18n must be used within an I18nProvider')
  }

  return context
}
